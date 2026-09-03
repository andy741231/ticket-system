<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        /* Drop cap styling - applied ONLY to paragraphs explicitly marked with
           the has-dropcap class in the email builder (matches editor preview).
           Do not use p:first-child here: every text block's first <p> is a
           :first-child of its wrapper div, which rendered phantom drop caps
           in clients that support :first-letter (e.g. Outlook for Mac). */
        .newsletter-content p.has-dropcap:first-letter {
            float: left;
            font-size: 3.5em;
            line-height: 0.8;
            margin: 0.1em 0.2em 0 0;
            color: #333;
            font-weight: bold;
            text-transform: uppercase;
        }

        /* Ensure proper spacing and text flow */
        .newsletter-content p.has-dropcap {
            overflow: hidden; /* Contains the floated drop cap */
        }

        /* Reset for mobile */
        @media screen and (max-width: 600px) {
            .newsletter-content p.has-dropcap:first-letter {
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
