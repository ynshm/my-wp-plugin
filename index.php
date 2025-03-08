
<?php
// Basic entry point for the plugin
header("Content-Type: text/html");
?>
<!DOCTYPE html>
<html>
<head>
    <title>LLM Traffic Optimizer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
        }
        .debug-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .debug-link:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>LLM Traffic Optimizer Plugin</h1>
        <p>This is the homepage for the LLM Traffic Optimizer WordPress plugin.</p>
        <p>This plugin helps optimize traffic from Large Language Models by generating summaries of your content and tracking AI-referral traffic.</p>
        
        <h2>Key Features:</h2>
        <ul>
            <li>Generate article summaries using OpenAI</li>
            <li>Track AI traffic to your website</li>
            <li>Create LLMS.txt files for better AI crawling</li>
            <li>Optimize your content for AI discovery</li>
        </ul>
        
        <a href="debug.php" class="debug-link">Run Debug Tool</a>
    </div>
</body>
</html>
