# AI Gateway

AI Gateway is a local-first WordPress plugin that connects Gutenberg to Ollama (and optional MCP backends). It lets you run AI agents inside the editor, apply native tools, and keep full control on your own infrastructure.

## Why it is useful
- Run AI agents directly in Gutenberg (no external SaaS required)
- Control which tools each agent can use
- Edit content, layout, and structure from a single sidebar
- Extend with MCP tools or your own endpoints

## Quick demo (30 seconds)
1. Open a post in Gutenberg and open the AI Gateway sidebar
2. Select an agent and click Generate
3. Insert the result or use native tools (Smart Edit, Outline, Template)

## Screenshots
- `docs/screenshots/sidebar.png`
- `docs/screenshots/tools.png`
- `docs/screenshots/agents.png`

## Security and privacy
- Local-first by design (Ollama runs on your own host)
- No content is sent to third-party APIs by default
- Admin-only actions for plugin activation and tools

## Core features
- Custom AI agents (model, system prompt, inputs, MCP endpoint)
- Gutenberg sidebar with AI assistant and native tools
- Tool permissions per agent (checkboxes)
- REST API for agents, runs, media import, and plugin actions
- Admin pages for agents, settings, and plugins

## Quick start
1. Upload the plugin folder to `wp-content/plugins/ai-gateway`
2. Activate it in WordPress Admin
3. Open a post in Gutenberg and use the AI Gateway sidebar

If you change `src/index.js`:
```bash
npm install
npm run build
```

## Tools available in Gutenberg
- Smart Edit (target block text + colors)
- Outline to Sections
- Page Template
- FAQ Builder
- CTA Builder
- Quick Palette
- Media Smart Insert
- Hero Section

## Agents
Create agents from the admin panel with:
- Model name (Ollama)
- System prompt
- Input schema (JSON -> form fields)
- Output mode (`text` or `blocks`)
- Allowed tools (checkboxes)

## Plugin control (admin)
AI Gateway provides a Plugins IA page and REST endpoints to:
- list installed plugins
- activate/deactivate plugins (admin only)

## REST API
Base: `/wp-json/ai/v1`
- `GET /ping`
- `POST /run`
- `POST /publish`
- `GET /agents`
- `POST /agents`
- `GET /agents/{id}`
- `PUT /agents/{id}`
- `DELETE /agents/{id}`
- `POST /media/import`
- `GET /plugins`
- `POST /plugins/activate`
- `POST /plugins/deactivate`

## Roadmap
- v2.1: tools per agent, plugins admin, sidebar native tools
- v2.2: workflows, presets, logs, export/import
- v3: marketplace + connectors

## License
GPL-2.0-or-later
