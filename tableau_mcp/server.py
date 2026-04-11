import os
import base64
import json
from dotenv import load_dotenv
import tableauserverclient as TSC
from mcp.server.fastmcp import FastMCP

load_dotenv()

mcp = FastMCP("tableau")


# ── helpers ───────────────────────────────────────────────────────────────────

def _server() -> TSC.Server:
    url = os.environ["TABLEAU_SERVER_URL"]
    srv = TSC.Server(url, use_server_version=True)
    # Disable SSL verification for internal/self-signed certs
    srv.add_http_options({"verify": False})
    return srv


def _auth() -> TSC.PersonalAccessTokenAuth:
    return TSC.PersonalAccessTokenAuth(
        token_name=os.environ["TABLEAU_TOKEN_NAME"],
        personal_access_token_secret=os.environ["TABLEAU_TOKEN_VALUE"],
        site_id=os.environ.get("TABLEAU_SITE_ID", ""),
    )


# ── tools ─────────────────────────────────────────────────────────────────────

@mcp.tool()
def list_workbooks(project_name: str = "") -> str:
    """List all workbooks on the Tableau site.

    Args:
        project_name: Optional. Filter by project name (case-insensitive substring match).

    Returns JSON list of workbooks with id, name, project, owner_id, content_url,
    created_at, and updated_at.
    """
    srv = _server()
    with srv.auth.sign_in(_auth()):
        req = TSC.RequestOptions(pagesize=1000)
        all_workbooks, _ = srv.workbooks.get(req)

    results = []
    for wb in all_workbooks:
        if project_name and project_name.lower() not in (wb.project_name or "").lower():
            continue
        results.append({
            "id": wb.id,
            "name": wb.name,
            "project": wb.project_name,
            "owner_id": wb.owner_id,
            "content_url": wb.content_url,
            "created_at": str(wb.created_at),
            "updated_at": str(wb.updated_at),
        })
    return json.dumps(results, indent=2)


@mcp.tool()
def list_views(workbook_id: str = "") -> str:
    """List views (sheets/dashboards) on the Tableau site.

    Args:
        workbook_id: Optional. If provided, return only views for that workbook.
                     If omitted, returns all views across all workbooks.

    Returns JSON list with id, name, content_url, owner_id, total_views.
    """
    srv = _server()
    with srv.auth.sign_in(_auth()):
        if workbook_id:
            wb = srv.workbooks.get_by_id(workbook_id)
            srv.workbooks.populate_views(wb)
            views = wb.views
        else:
            req = TSC.RequestOptions(pagesize=1000)
            views, _ = srv.views.get(req)

    results = []
    for v in views:
        results.append({
            "id": v.id,
            "name": v.name,
            "content_url": v.content_url,
            "owner_id": v.owner_id,
            "total_views": v.total_views,
        })
    return json.dumps(results, indent=2)


@mcp.tool()
def get_view_image(view_id: str, resolution: str = "high") -> str:
    """Download a Tableau view as a PNG image for visual analysis.

    Args:
        view_id: The view ID (obtain from list_views).
        resolution: Image resolution — "high" (default, 2x) or "standard" (1x).

    Returns a base64-encoded PNG string prefixed with "data:image/png;base64,".
    Claude can display this image inline.
    """
    image_req = TSC.ImageRequestOptions(
        imageresolution=TSC.ImageRequestOptions.Resolution.High
        if resolution == "high"
        else TSC.ImageRequestOptions.Resolution.Standard
    )
    srv = _server()
    with srv.auth.sign_in(_auth()):
        view = srv.views.get_by_id(view_id)
        srv.views.populate_image(view, image_req)
        png_bytes = view.image

    encoded = base64.b64encode(png_bytes).decode("utf-8")
    return f"data:image/png;base64,{encoded}"


@mcp.tool()
def get_view_data(view_id: str, filters: str = "") -> str:
    """Download the underlying data from a Tableau view as CSV.

    Args:
        view_id: The view ID (obtain from list_views).
        filters: Optional. Comma-separated key=value filter pairs applied to the view.
                 Example: "Region=East,Year=2024"

    Returns a CSV string of the view's data for analysis.
    """
    csv_req = TSC.CSVRequestOptions()
    if filters:
        for pair in filters.split(","):
            pair = pair.strip()
            if "=" in pair:
                key, value = pair.split("=", 1)
                csv_req.vf(key.strip(), value.strip())

    srv = _server()
    with srv.auth.sign_in(_auth()):
        view = srv.views.get_by_id(view_id)
        srv.views.populate_csv(view, csv_req)
        csv_bytes = b"".join(view.csv)

    return csv_bytes.decode("utf-8", errors="replace")


@mcp.tool()
def list_datasources(project_name: str = "") -> str:
    """List published data sources on the Tableau site.

    Args:
        project_name: Optional. Filter by project name (case-insensitive substring match).

    Returns JSON list with id, name, type, project, content_url, updated_at.
    """
    srv = _server()
    with srv.auth.sign_in(_auth()):
        req = TSC.RequestOptions(pagesize=1000)
        datasources, _ = srv.datasources.get(req)

    results = []
    for ds in datasources:
        if project_name and project_name.lower() not in (ds.project_name or "").lower():
            continue
        results.append({
            "id": ds.id,
            "name": ds.name,
            "type": ds.datasource_type,
            "project": ds.project_name,
            "content_url": ds.content_url,
            "updated_at": str(ds.updated_at),
        })
    return json.dumps(results, indent=2)


@mcp.tool()
def get_workbook_info(workbook_id: str) -> str:
    """Get detailed information about a specific workbook including all its views and connections.

    Args:
        workbook_id: The workbook ID (obtain from list_workbooks).

    Returns JSON with full workbook metadata, list of views, and data connections.
    """
    srv = _server()
    with srv.auth.sign_in(_auth()):
        wb = srv.workbooks.get_by_id(workbook_id)
        srv.workbooks.populate_views(wb)
        srv.workbooks.populate_connections(wb)

    info = {
        "id": wb.id,
        "name": wb.name,
        "project": wb.project_name,
        "owner_id": wb.owner_id,
        "content_url": wb.content_url,
        "show_tabs": wb.show_tabs,
        "size": wb.size,
        "created_at": str(wb.created_at),
        "updated_at": str(wb.updated_at),
        "views": [
            {"id": v.id, "name": v.name, "content_url": v.content_url}
            for v in wb.views
        ],
        "connections": [
            {
                "id": c.id,
                "type": c.connection_type,
                "server": c.server_address,
            }
            for c in wb.connections
        ],
    }
    return json.dumps(info, indent=2)


if __name__ == "__main__":
    mcp.run(transport="stdio")
