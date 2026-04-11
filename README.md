# Tableau MCP Server

An MCP (Model Context Protocol) server that connects Claude to Tableau Server at `https://tableau.razorpay.in`. Use it to browse workbooks, fetch view screenshots, and pull underlying data — all via natural language in Claude.

## Setup

### 1. Install dependencies

```bash
pip install -r requirements.txt
```

### 2. Configure credentials

```bash
cp .env.example .env
# then edit .env with your credentials
```

Create a Personal Access Token in Tableau Server:
> Top-right menu → **Account Settings** → **Personal Access Tokens** → **Create new token**

### 3. Run the server

```bash
python -m tableau_mcp.server
```

---

## Claude Desktop Integration

Add to `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS) or  
`%APPDATA%\Claude\claude_desktop_config.json` (Windows):

```json
{
  "mcpServers": {
    "tableau": {
      "command": "python",
      "args": ["-m", "tableau_mcp.server"],
      "cwd": "/path/to/this/repo",
      "env": {
        "TABLEAU_SERVER_URL": "https://tableau.razorpay.in",
        "TABLEAU_SITE_ID": "SG",
        "TABLEAU_TOKEN_NAME": "your-token-name",
        "TABLEAU_TOKEN_VALUE": "your-token-secret"
      }
    }
  }
}
```

Restart Claude Desktop after saving.

---

## Available Tools

| Tool | Description |
|------|-------------|
| `list_workbooks` | List all workbooks; optional `project_name` filter |
| `list_views` | List views/dashboards; optional `workbook_id` filter |
| `get_view_image` | Download a view as a base64 PNG (Claude renders it inline) |
| `get_view_data` | Download view data as CSV; optional `filters` (e.g. `"Region=East"`) |
| `list_datasources` | List published data sources; optional `project_name` filter |
| `get_workbook_info` | Full workbook details: views + data connections |

---

## Testing with MCP Inspector

```bash
npx @modelcontextprotocol/inspector python -m tableau_mcp.server
```

---

## Notes

- SSL verification is disabled (`verify=False`) for the internal self-signed certificate on `tableau.razorpay.in`.
- PATs expire after **15 days of inactivity** — rotate them in Account Settings if authentication fails.
- Each tool call opens a fresh Tableau session (sign-in → call → sign-out).
