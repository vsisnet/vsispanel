<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 - Service Unavailable | VSISPanel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #333; }
        .container { text-align: center; padding: 2rem; }
        .icon { font-size: 4rem; margin-bottom: 1rem; }
        .title { font-size: 1.5rem; font-weight: 600; margin: 1rem 0 0.5rem; color: #1A5276; }
        .desc { color: #666; margin-bottom: 2rem; max-width: 400px; }
        .retry { display: inline-block; padding: 0.75rem 2rem; background: #1A5276; color: #fff; text-decoration: none; border-radius: 0.5rem; font-weight: 500; cursor: pointer; border: none; font-size: 1rem; transition: background 0.2s; }
        .retry:hover { background: #154360; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">&#128736;</div>
        <h1 class="title">Maintenance Mode</h1>
        <p class="desc">VSISPanel is currently undergoing maintenance. We'll be back shortly.</p>
        <button class="retry" onclick="location.reload()">Retry</button>
    </div>
</body>
</html>
