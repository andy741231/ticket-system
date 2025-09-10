<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        /* Drop cap styling for first paragraph */
        .newsletter-content p:first-child:first-letter {
            float: left;
            font-size: 3.5em;
            line-height: 0.8;
            margin: 0.1em 0.2em 0 0;
            color: #333;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        /* Ensure proper spacing and text flow */
        .newsletter-content p:first-child {
            overflow: hidden; /* Contains the floated drop cap */
        }
        
        /* Reset for mobile */
        @media screen and (max-width: 600px) {
            .newsletter-content p:first-child:first-letter {
                font-size: 2.5em;
                line-height: 1;
            }
        }
    </style>
</head>
<body>
    <div class="newsletter-content">
        <?php echo $htmlContent; ?>
    </div>
</body>
</html>
