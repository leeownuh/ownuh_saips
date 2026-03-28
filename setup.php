<?php
declare(strict_types=1);

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');

echo "setup.php is retired.\n";
echo "\n";
echo "Use one of the canonical setup scripts instead:\n";
echo "- Windows: setup_windows.ps1 or setup_windows.bat\n";
echo "- Linux:   install.sh\n";
echo "\n";
echo "These scripts now own database creation, seed import, key generation, and .env setup.\n";
