<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Documentation – Voice Without Fear</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body { margin: 0; }
        #swagger-header {
            background: #9d2722;
            color: #fff;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        #swagger-header a {
            color: #fff;
            text-decoration: none;
            font-size: 0.9rem;
            opacity: 0.85;
        }
        #swagger-header a:hover { opacity: 1; }
        #swagger-header h1 { margin: 0; font-size: 1.2rem; font-weight: 600; flex: 1; }
    </style>
</head>
<body>
<div id="swagger-header">
    <h1>Voice Without Fear – API Documentation</h1>
    <a href="/">&#8592; Back to App</a>
</div>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script>
    SwaggerUIBundle({
        url: '/api/openapi.json',
        dom_id: '#swagger-ui',
        presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
        layout: 'BaseLayout',
        deepLinking: true,
        defaultModelsExpandDepth: 1,
        defaultModelExpandDepth: 2,
        docExpansion: 'list',
        filter: true
    });
</script>
</body>
</html>
