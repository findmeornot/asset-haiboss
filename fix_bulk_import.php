<?php
$file = 'app/Services/AssetImportService.php';
$content = file_get_contents($file);

$oldLine = "\$assetData['id']               = \$ulid; // Assign ULID manually";
$newLine = "\$assetData['ulid']             = \$ulid; // Assign ULID manually";

$content = str_replace($oldLine, $newLine, $content);
file_put_contents($file, $content);
echo "Fixed ulid assignment!\n";
