# AI Gateway

AI Gateway is a local-first WordPress plugin that connects Gutenberg to Ollama (and optional MCP backends) with AI agents, native editor tools, and REST endpoints.

## Highlights
- Custom AI agents with models, prompts, inputs, and MCP endpoints
- Gutenberg sidebar with AI assistant + native tools
- Tool permissions per agent (enable only what each agent can do)
- REST API for agents, runs, media import, and plugin activation

## Requirements
- WordPress 6.x
- PHP 8.0+
- Node.js (for building assets)

## Install
1. Upload the plugin folder to `wp-content/plugins/ai-gateway`
2. Activate from WordPress Admin
3. Build assets if you change `src/index.js`:
   ```bash
   npm install
   npm run build
   ```

## Build
```bash
npm install
npm run build
```

## Tools (Gutenberg)
- Smart Edit (target block text + colors)
- Outline to Sections
- Page Template
- FAQ Builder
- CTA Builder
- Quick Palette
- Media Smart Insert
- Hero Section

## REST endpoints
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