<?php
http_response_code(500);
throw new Exception("Test error " . ($_GET['i'] ?? 'no-id'));
