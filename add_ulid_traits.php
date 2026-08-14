<?php

$models = [
    'app/Models/Role.php',
    'app/Models/Permission.php',
    'app/Models/Asset.php',
    'app/Models/Category.php',
    'app/Models/Campus.php',
    'app/Models/Location.php',
    'app/Models/Employee.php',
    'app/Models/AssetMovement.php',
    'app/Models/StockOpname.php',
    'app/Models/ApprovalRequest.php',
    'app/Models/AuditLog.php',
];

foreach ($models as $modelPath) {
    if (!file_exists($modelPath)) continue;
    
    $content = file_get_contents($modelPath);
    
    // Add use App\Models\Traits\HasRouteUlid;
    if (strpos($content, 'use App\Models\Traits\HasRouteUlid;') === false) {
        $content = preg_replace(
            '/use Illuminate\\\\Database\\\\Eloquent\\\\Model;/',
            "use Illuminate\\Database\\Eloquent\\Model;\nuse App\\Models\\Traits\\HasRouteUlid;",
            $content
        );
        // For Role and Permission that might extend Spatie models
        $content = preg_replace(
            '/use Spatie\\\\Permission\\\\Models\\\\(Role|Permission);/',
            "use Spatie\\Permission\\Models\\$1;\nuse App\\Models\\Traits\\HasRouteUlid;",
            $content
        );
    }
    
    // Add use HasRouteUlid; inside the class
    if (strpos($content, 'use HasRouteUlid;') === false) {
        // Find class { and insert right after
        $content = preg_replace(
            '/class\s+[a-zA-Z0-9_]+\s*(extends\s+[a-zA-Z0-9_\\\\]+\s*)?(\s*implements\s+[a-zA-Z0-9_\\\\\s,]+)?\s*\{/',
            "$0\n    use HasRouteUlid;\n",
            $content
        );
    }
    
    file_put_contents($modelPath, $content);
}

echo "Done\n";
