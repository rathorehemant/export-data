<?php
require_once 'db_connect.php';

// Check if action parameter is set
if (isset($_GET['action']) && !empty($_GET['action'])) {
    $action = $_GET['action'];

    switch ($action) {
        case 'export_sql':
            exportDatabase($conn, $database);
            break;

        case 'drop_sql':
            dropDatabase($conn, $database);
            break;

        default:
            echo "Invalid action.";
            break;
    }
}

/**
 * Exports the database including table structure, data, and foreign key constraints.
 *
 * @param mysqli $conn The database connection.
 * @param string $database The name of the database to export.
 */
function exportDatabase($conn, $database)
{
    // Get all tables in the database
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }

    // Prepare SQL dump header
    $sql_dump = "-- Database Export: $database\n";
    $sql_dump .= "-- Export Date: " . date('Y-m-d H:i:s') . "\n\n";
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n"; // Disable foreign key checks before export

    // Loop through each table
    foreach ($tables as $table) {
        // Get table creation query
        $createTableResult = $conn->query("SHOW CREATE TABLE `$table`");
        $createTableRow = $createTableResult->fetch_assoc();
        $sql_dump .= "\n\n" . $createTableRow['Create Table'] . ";\n\n";

        // Get table data
        $dataResult = $conn->query("SELECT * FROM `$table`");
        while ($row = $dataResult->fetch_assoc()) {
            $values = array_map(fn($value) => "'" . $conn->real_escape_string($value) . "'", $row);
            $sql_dump .= "INSERT INTO `$table` VALUES (" . implode(", ", $values) . ");\n";
        }
    }

    // Append Foreign Key Constraints
    $sql_dump .= "\n\n-- Foreign Key Constraints\n\n";
    $foreignKeyQuery = "
        SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = '$database' AND REFERENCED_TABLE_NAME IS NOT NULL
    ";
    $fkResult = $conn->query($foreignKeyQuery);

    $alterTableStatements = [];
    while ($fkRow = $fkResult->fetch_assoc()) {
        $tableName = $fkRow['TABLE_NAME'];
        $columnName = $fkRow['COLUMN_NAME'];
        $constraintName = $fkRow['CONSTRAINT_NAME'];
        $referencedTable = $fkRow['REFERENCED_TABLE_NAME'];
        $referencedColumn = $fkRow['REFERENCED_COLUMN_NAME'];

        $alterTableStatements[$tableName][] = "ADD CONSTRAINT `$constraintName` FOREIGN KEY (`$columnName`) REFERENCES `$referencedTable` (`$referencedColumn`) ON DELETE CASCADE ON UPDATE CASCADE";
    }

    // Append ALTER TABLE statements
    foreach ($alterTableStatements as $table => $constraints) {
        $sql_dump .= "ALTER TABLE `$table`\n  " . implode(",\n  ", $constraints) . ";\n\n";
    }

    $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n"; // Re-enable foreign key checks
    $sql_dump .= "COMMIT;\n"; // Commit the transaction

    // Close the database connection
    $conn->close();

    // Define the filename
    $backupFile = "database_backup_" . date("Y-m-d_H-i-s") . ".sql";

    // Download the SQL file
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $backupFile . '"');
    header('Content-Length: ' . strlen($sql_dump));
    echo $sql_dump;
    exit;
}

/**
 * Drops the database.
 *
 * @param mysqli $conn The database connection.
 * @param string $database The name of the database to drop.
 */
function dropDatabase($conn, $database)
{
    $sql = "DROP DATABASE `$database`";
    if ($conn->query($sql) === TRUE) {
        echo "Database `$database` dropped successfully.";
    } else {
        echo "Error dropping database: " . $conn->error;
    }

    // Close the database connection
    $conn->close();
}
?>
