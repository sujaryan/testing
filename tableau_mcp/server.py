"""
Tableau MCP Server

Provides MCP tools for Claude to interact with Tableau workbooks,
views, and datasources via the Tableau REST API.

Run with:
    python -m tableau_mcp.server
"""

from __future__ import annotations

import json
import logging
from contextlib import asynccontextmanager
from typing import AsyncIterator

import anyio
from dotenv import load_dotenv
from mcp.server.fastmcp import FastMCP, Image

from tableau_mcp.tableau_client import TableauClient

load_dotenv()

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


# ── Lifespan: sign in once at startup, sign out at shutdown ───────────────────

@asynccontextmanager
async def lifespan(app: FastMCP) -> AsyncIterator[dict]:
    """
    Signs in to Tableau Server when the MCP server starts and signs out
    when it stops. The signed-in TableauClient is passed to each tool via
    the lifespan context.
    """
    client = TableauClient()
    sign_in_cm = client.server.auth.sign_in(client.auth)
    # sign_in is synchronous/blocking — run it off the async event loop
    await anyio.to_thread.run_sync(sign_in_cm.__enter__)
    logger.info("Signed in to Tableau Server.")
    try:
        yield {"client": client}
    finally:
        await anyio.to_thread.run_sync(lambda: sign_in_cm.__exit__(None, None, None))
        logger.info("Signed out of Tableau Server.")


# ── FastMCP app ────────────────────────────────────────────────────────────────

mcp = FastMCP(
    name="tableau",
    instructions=(
        "Interact with Tableau Server at https://tableau.razorpay.in (site: SG). "
        "Start with list_workbooks to discover available workbooks, then list_views "
        "to find views within a workbook. Use get_view_image to see a visualization "
        "as an image, get_view_data to retrieve the underlying data as CSV, "
        "list_datasources to see published data sources, and get_workbook_info "
        "for full workbook details including all views and data connections."
    ),
    lifespan=lifespan,
)


def _client(ctx) -> TableauClient:
    return ctx.request_context.lifespan_context["client"]


# ── Tools ──────────────────────────────────────────────────────────────────────

@mcp.tool()
async def list_workbooks(ctx) -> str:
    """
    List all workbooks published on the Tableau site.

    Returns a JSON array. Each element contains:
      - id: Workbook LUID — use this in get_workbook_info and list_views
      - name: Human-readable workbook name
      - project_name / project_id: Project the workbook lives in
      - owner_id: LUID of the owner
      - created_at / updated_at: ISO 8601 timestamps
      - content_url: URL-safe name used in Tableau URLs
      - webpage_url: Direct link to the workbook on Tableau Server
      - tags: List of tags

    All workbooks across all pages are returned. Call this first to
    discover available workbooks before drilling into views.
    """
    client = _client(ctx)
    result = await anyio.to_thread.run_sync(client.list_workbooks)
    return json.dumps(result, indent=2)


@mcp.tool()
async def list_views(ctx, workbook_id: str = "") -> str:
    """
    List views (sheets/dashboards) on the Tableau site.

    Args:
        workbook_id: Optional. If provided, returns only views for that
                     workbook. If omitted, returns all views on the site.

    Returns a JSON array. Each element contains:
      - id: View LUID — use this in get_view_image and get_view_data
      - name: Human-readable view name
      - content_url: URL-safe path for the view
      - workbook_id: Parent workbook LUID
      - sheet_type: "worksheet", "dashboard", or "story"
      - created_at / updated_at: ISO 8601 timestamps
      - tags: List of tags

    Tip: pass a workbook_id from list_workbooks to scope results.
    """
    client = _client(ctx)
    result = await anyio.to_thread.run_sync(
        lambda: client.list_views(workbook_id=workbook_id or None)
    )
    return json.dumps(result, indent=2)


@mcp.tool()
async def get_view_image(ctx, view_id: str, resolution: str = "high") -> Image:
    """
    Download a Tableau view as a PNG image for visual analysis.

    Args:
        view_id: The view LUID (from list_views).
        resolution: "high" (default, 2x/retina) or "standard" (1x).

    Returns the view rendered as a PNG image showing live data.
    Use this to visually inspect charts, dashboards, or worksheets.
    After receiving the image, describe what you see or answer
    questions about the data visualization.

    Note: Large dashboards may take a few seconds to render.
    """
    client = _client(ctx)
    image_bytes = await anyio.to_thread.run_sync(
        lambda: client.get_view_image(view_id=view_id, resolution=resolution)
    )
    return Image(data=image_bytes, format="png")


@mcp.tool()
async def get_view_data(ctx, view_id: str, filters: str = "") -> str:
    """
    Download the underlying data from a Tableau view as CSV.

    Args:
        view_id: The view LUID (from list_views).
        filters: Optional comma-separated key=value pairs applied as view
                 filters. Example: "Region=East,Year=2024"

    Returns a CSV string (first row = headers, subsequent rows = data).
    Use this to analyze exact numbers, export data, or answer precise
    quantitative questions about what's in a view.
    """
    client = _client(ctx)
    csv_text = await anyio.to_thread.run_sync(
        lambda: client.get_view_data(view_id=view_id, filters=filters)
    )
    return csv_text


@mcp.tool()
async def list_datasources(ctx) -> str:
    """
    List all published datasources on the Tableau site.

    Returns a JSON array. Each element contains:
      - id: Datasource LUID
      - name: Human-readable datasource name
      - project_name / project_id: Project the datasource lives in
      - datasource_type: Connector type (e.g. "postgres", "bigquery")
      - description: Optional description
      - has_extracts: Whether the datasource uses extract-based data
      - certified: Whether it is certified as a trusted source
      - created_at / updated_at: ISO 8601 timestamps
      - webpage_url: Direct link on Tableau Server
      - tags: List of tags

    Use this to discover what data is available and understand which
    workbooks might connect to which sources.
    """
    client = _client(ctx)
    result = await anyio.to_thread.run_sync(client.list_datasources)
    return json.dumps(result, indent=2)


@mcp.tool()
async def get_workbook_info(ctx, workbook_id: str) -> str:
    """
    Get detailed information about a specific Tableau workbook.

    Args:
        workbook_id: The workbook LUID (from list_workbooks).

    Returns a JSON object with:
      - Basic fields: id, name, project_name, owner_id, timestamps, tags
      - show_tabs / has_extracts: Structural properties
      - webpage_url: Direct link to the workbook
      - views: Array of {id, name, content_url, sheet_type} — use the
               view id values with get_view_image and get_view_data
      - connections: Array of {id, datasource_name, connection_type,
                     server_address} — shows what data the workbook uses

    Use this to explore a workbook's structure before working with
    individual views.
    """
    client = _client(ctx)
    result = await anyio.to_thread.run_sync(
        lambda: client.get_workbook_info(workbook_id=workbook_id)
    )
    return json.dumps(result, indent=2)


if __name__ == "__main__":
    mcp.run(transport="stdio")
