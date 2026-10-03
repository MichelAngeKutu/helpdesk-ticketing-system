<?php
// Copy this file to config.php and fill in your own values.
// config.php is listed in .gitignore so your password never reaches GitHub.
return [
    'app_name' => 'Help Desk',
    'db' => [
        // Windows + SQL Server Express with Microsoft's PDO_SQLSRV driver
        'dsn'  => 'sqlsrv:Server=localhost\\SQLEXPRESS;Database=HelpDesk;TrustServerCertificate=1',
        'user' => 'your_sql_login',
        'pass' => 'your_sql_password',
    ],
];
