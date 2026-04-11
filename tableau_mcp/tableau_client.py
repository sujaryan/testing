"""
Tableau API client wrapper using the official tableauserverclient (TSC) library.

All public methods must be called while the server is signed in (i.e., inside
a `with server.auth.sign_in(...)` block). The MCP server manages this via the
FastMCP lifespan mechanism.
"""

from __future__ import annotations

import logging
import os

import tableauserverclient as TSC

logger = logging.getLogger(__name__)


def _build_auth() -> TSC.Credentials:
    """
    Build a TSC credentials object from environment variables.

    Priority: PAT (TABLEAU_TOKEN_NAME + TABLEAU_TOKEN_VALUE) over
    username/password (TABLEAU_USERNAME + TABLEAU_PASSWORD).
    """
    site_id = os.environ.get("TABLEAU_SITE_ID", "").strip()

    token_name = os.environ.get("TABLEAU_TOKEN_NAME", "").strip()
    token_value = os.environ.get("TABLEAU_TOKEN_VALUE", "").strip()
    if token_name and token_value:
        logger.info("Using Personal Access Token authentication.")
        return TSC.PersonalAccessTokenAuth(
            token_name=token_name,
            personal_access_token_secret=token_value,
            site_id=site_id,
        )

    username = os.environ.get("TABLEAU_USERNAME", "").strip()
    password = os.environ.get("TABLEAU_PASSWORD", "").strip()
    if username and password:
        logger.info("Using username/password authentication.")
        return TSC.TableauAuth(username=username, password=password, site_id=site_id)

    raise EnvironmentError(
        "No Tableau credentials found. Set TABLEAU_TOKEN_NAME + TABLEAU_TOKEN_VALUE "
        "or TABLEAU_USERNAME + TABLEAU_PASSWORD."
    )


class TableauClient:
    """
    Synchronous Tableau API client. All methods assume the TSC server
    is already signed in (managed externally via lifespan).
    """

    def __init__(self) -> None:
        server_url = os.environ.get("TABLEAU_SERVER_URL", "").strip()
        if not server_url:
            raise EnvironmentError("TABLEAU_SERVER_URL environment variable is required.")

        self.server = TSC.Server(server_url, use_server_version=True)
        # Disable SSL verification for self-signed / internal certs if requested
        if os.environ.get("TABLEAU_DISABLE_SSL_VERIFY", "").lower() in ("1", "true", "yes"):
            self.server.add_http_options({"verify": False})
        else:
            # Internal Razorpay Tableau uses a self-signed cert; disable by default
            self.server.add_http_options({"verify": False})

        self.auth = _build_auth()

    # ── Workbooks ──────────────────────────────────────────────────────────

    def list_workbooks(self) -> list[dict]:
        """Return all workbooks on the site (all pages via TSC.Pager)."""
        results = []
        for wb in TSC.Pager(self.server.workbooks):
            results.append({
                "id": wb.id,
                "name": wb.name,
                "project_name": wb.project_name,
                "project_id": wb.project_id,
                "owner_id": wb.owner_id,
                "created_at": wb.created_at.isoformat() if wb.created_at else None,
                "updated_at": wb.updated_at.isoformat() if wb.updated_at else None,
                "content_url": wb.content_url,
                "webpage_url": wb.webpage_url,
                "tags": sorted(wb.tags) if wb.tags else [],
            })
        return results

    def get_workbook_info(self, workbook_id: str) -> dict:
        """Return detailed info for a workbook, including views and connections."""
        wb = self.server.workbooks.get_by_id(workbook_id)
        self.server.workbooks.populate_views(wb)
        self.server.workbooks.populate_connections(wb)
        return {
            "id": wb.id,
            "name": wb.name,
            "project_name": wb.project_name,
            "project_id": wb.project_id,
            "owner_id": wb.owner_id,
            "created_at": wb.created_at.isoformat() if wb.created_at else None,
            "updated_at": wb.updated_at.isoformat() if wb.updated_at else None,
            "content_url": wb.content_url,
            "webpage_url": wb.webpage_url,
            "show_tabs": wb.show_tabs,
            "has_extracts": wb.has_extracts,
            "tags": sorted(wb.tags) if wb.tags else [],
            "views": [
                {
                    "id": v.id,
                    "name": v.name,
                    "content_url": v.content_url,
                    "sheet_type": v.sheet_type,
                }
                for v in wb.views
            ],
            "connections": [
                {
                    "id": c.id,
                    "datasource_name": c.datasource_name,
                    "connection_type": c.connection_type,
                    "server_address": c.server_address,
                }
                for c in (wb.connections or [])
            ],
        }

    # ── Views ──────────────────────────────────────────────────────────────

    def list_views(self, workbook_id: str | None = None) -> list[dict]:
        """Return views on the site, optionally filtered to a workbook."""
        if workbook_id:
            wb = self.server.workbooks.get_by_id(workbook_id)
            self.server.workbooks.populate_views(wb)
            views = wb.views
        else:
            views = list(TSC.Pager(self.server.views))

        return [
            {
                "id": v.id,
                "name": v.name,
                "content_url": v.content_url,
                "workbook_id": v.workbook_id,
                "owner_id": v.owner_id,
                "project_id": v.project_id,
                "created_at": v.created_at.isoformat() if v.created_at else None,
                "updated_at": v.updated_at.isoformat() if v.updated_at else None,
                "sheet_type": v.sheet_type,
                "tags": sorted(v.tags) if v.tags else [],
            }
            for v in views
        ]

    def get_view_image(self, view_id: str, resolution: str = "high") -> bytes:
        """
        Download a view as PNG bytes.

        Note: view.image makes a fresh HTTP request on each access (TSC lazy-loads it).
        Capture the bytes immediately after populate_image.
        """
        view = self.server.views.get_by_id(view_id)
        req_options = TSC.ImageRequestOptions(
            imageresolution=TSC.ImageRequestOptions.Resolution.High
            if resolution == "high"
            else TSC.ImageRequestOptions.Resolution.Standard
        )
        self.server.views.populate_image(view, req_options)
        return view.image  # bytes

    def get_view_data(self, view_id: str, filters: str = "") -> str:
        """
        Download view data as a CSV string.

        Note: view.csv is an Iterator[bytes] that yields 1024-byte chunks.
        Consume it immediately with b"".join() — iterating twice will fail
        because the HTTP response stream is closed after the first pass.
        """
        view = self.server.views.get_by_id(view_id)
        csv_req = TSC.CSVRequestOptions()
        if filters:
            for pair in filters.split(","):
                pair = pair.strip()
                if "=" in pair:
                    key, value = pair.split("=", 1)
                    csv_req.vf(key.strip(), value.strip())
        self.server.views.populate_csv(view, csv_req)
        return b"".join(view.csv).decode("utf-8", errors="replace")

    # ── Datasources ────────────────────────────────────────────────────────

    def list_datasources(self) -> list[dict]:
        """Return all published datasources on the site (all pages via TSC.Pager)."""
        results = []
        for ds in TSC.Pager(self.server.datasources):
            results.append({
                "id": ds.id,
                "name": ds.name,
                "project_name": ds.project_name,
                "project_id": ds.project_id,
                "owner_id": ds.owner_id,
                "datasource_type": ds.datasource_type,
                "description": ds.description,
                "has_extracts": ds.has_extracts,
                "certified": ds.certified,
                "created_at": ds.created_at.isoformat() if ds.created_at else None,
                "updated_at": ds.updated_at.isoformat() if ds.updated_at else None,
                "webpage_url": ds.webpage_url,
                "tags": sorted(ds.tags) if ds.tags else [],
            })
        return results
