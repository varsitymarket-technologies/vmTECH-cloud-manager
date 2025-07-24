<?php

/**
 * SQLiteManager Class
 *
 * This class provides a simple interface for managing a SQLite database
 * from a PHP CLI application. It handles database creation, table creation,
 * and general SQL query execution.
 */
class SQLiteManager
{
    private PDO $pdo; // PDO object for database connection
    private string $dbPath; // Path to the SQLite database file

    /**
     * Constructor for SQLiteManager.
     *
     * @param string $dbFileName The name of the SQLite database file (e.g., 'mydatabase.sqlite').
     */
    public function __construct(string $dbFileName = 'database.sqlite')
    {
        // Define the database path relative to the script's execution directory
        $this->dbPath = __DIR__ . DIRECTORY_SEPARATOR . $dbFileName;
        $this->connect(); // Establish database connection
    }

    /**
     * Connects to the SQLite database.
     * If the database file does not exist, it will be created.
     */
    private function connect(): void
    {
        try {
            // Check if the database file exists, if not, it will be created by PDO
            if (!file_exists($this->dbPath)) {
                echo "Creating new SQLite database: " . $this->dbPath . "\n";
                // No need to explicitly create the file, PDO will do it on connection
            }

            // Establish PDO connection to the SQLite database
            $this->pdo = new PDO("sqlite:" . $this->dbPath);
            // Set error mode to exceptions for better error handling
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "Successfully connected to SQLite database: " . $this->dbPath . "\n";
        } catch (PDOException $e) {
            // Catch and display any connection errors
            echo "Database connection failed: " . $e->getMessage() . "\n";
            exit(1); // Exit the script on connection failure
        }
    }

    /**
     * Executes a CREATE TABLE SQL statement.
     *
     * @param string $tableName The name of the table to create.
     * @param array $columns An associative array where keys are column names and values are their types (e.g., ['id' => 'INTEGER PRIMARY KEY AUTOINCREMENT', 'name' => 'TEXT']).
     * @return bool True on success, false on failure.
     */
    public function createTable(string $tableName, array $columns): bool
    {
        if (empty($columns)) {
            echo "Error: No columns provided for table creation.\n";
            return false;
        }

        // Build the column definitions string
        $columnDefinitions = [];
        foreach ($columns as $columnName => $columnType) {
            $columnDefinitions[] = "$columnName $columnType";
        }
        $columnsSql = implode(", ", $columnDefinitions);

        // Construct the CREATE TABLE SQL query
        $sql = "CREATE TABLE IF NOT EXISTS $tableName ($columnsSql);";

        echo "Attempting to create table: $tableName with SQL: $sql\n";
        try {
            // Execute the SQL statement
            $this->pdo->exec($sql);
            echo "Table '$tableName' created successfully (or already exists).\n";
            return true;
        } catch (PDOException $e) {
            // Catch and display any errors during table creation
            echo "Error creating table '$tableName': " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Executes any arbitrary SQL statement (INSERT, UPDATE, DELETE, etc.).
     *
     * @param string $sql The SQL statement to execute.
     * @return int|false The number of affected rows for INSERT/UPDATE/DELETE, or false on error.
     */
    public function executeSql(string $sql): int|false
    {
        echo "Executing SQL: $sql\n";
        try {
            // Execute the SQL statement and return affected rows
            $affectedRows = $this->pdo->exec($sql);
            echo "SQL executed successfully. Affected rows: $affectedRows\n";
            return $affectedRows;
        } catch (PDOException $e) {
            // Catch and display any errors during SQL execution
            echo "Error executing SQL: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Executes a SELECT query and returns the results.
     *
     * @param string $sql The SELECT SQL query.
     * @param array $params Optional array of parameters for prepared statements.
     * @return array An array of associative arrays representing the query results.
     */
    public function query(string $sql, array $params = []): array
    {
        echo "Executing query: $sql\n";
        try {
            // Prepare the SQL statement
            $stmt = $this->pdo->prepare($sql);
            // Execute the statement with parameters
            $stmt->execute($params);
            // Fetch all results as associative arrays
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "Query executed successfully. Found " . count($results) . " rows.\n";
            return $results;
        } catch (PDOException $e) {
            // Catch and display any errors during query execution
            echo "Error executing query: " . $e->getMessage() . "\n";
            return [];
        }
    }

    /**
     * Displays a help message for the CLI application.
     */
    public function showHelp(): void
    {
        echo "\n--- SQLite Database Manager CLI Help ---\n";
        echo "----------------------------------------\n";
        echo "Description: This CLI application allows you to manage a SQLite database. You can manage Databases Using This application and SQL commands\n";
        echo "Available commands:\n";
        echo "  help                               - Show this help message.\n";
        echo "  create_table <table_name> <col1:type1> <col2:type2> ... - Create a new table.\n";
        echo "                                       Example: create_table users id:INTEGER_PRIMARY_KEY_AUTOINCREMENT name:TEXT age:INTEGER\n";
        echo "                                       Note: Use underscores instead of spaces for column types (e.g., INTEGER_PRIMARY_KEY_AUTOINCREMENT).\n";
        echo "  exec_sql <SQL_statement>           - Execute any SQL statement (e.g., INSERT, UPDATE, DELETE).\n";
        echo "                                       Example: exec_sql \"INSERT INTO users (name, age) VALUES ('Alice', 30);\"\n";
        echo "  query <SQL_statement>              - Execute a SELECT query and display results.\n";
        echo "                                       Example: query \"SELECT * FROM users;\"\n";
        echo "  exit                               - Exit the application.\n";
        echo "----------------------------------------\n";
    }
}

// --- CLI Application Logic ---

// Create an instance of the SQLiteManager
$manager = new SQLiteManager('examples.sqlite');
$manager->showHelp(); // Show initial help message

// Start the CLI interaction loop
while (true) {
    echo "\nEnter command (type 'help' for options): ";
    // Read user input from the console
    $line = trim(fgets(STDIN));
    // Split the input into command and arguments
    $parts = explode(' ', $line, 2); // Split only on the first space to get command and rest of the line
    $command = strtolower($parts[0]);
    $args = $parts[1] ?? ''; // Get the rest of the line as arguments

    switch ($command) {
        case 'help':
            $manager->showHelp();
            break;

        case 'create_table':
            // Parse table name and column definitions
            $tableArgs = explode(' ', $args);
            $tableName = array_shift($tableArgs); // First argument is table name

            if (empty($tableName) || empty($tableArgs)) {
                echo "Usage: create_table <table_name> <col1:type1> <col2:type2> ...\n";
                break;
            }

            $columns = [];
            foreach ($tableArgs as $colDef) {
                $colParts = explode(':', $colDef, 2);
                if (count($colParts) === 2) {
                    // Replace underscores with spaces for column types
                    $columns[$colParts[0]] = str_replace('_', ' ', $colParts[1]);
                } else {
                    echo "Invalid column definition: '$colDef'. Expected format: name:type\n";
                    $columns = []; // Clear columns to prevent partial table creation
                    break;
                }
            }

            if (!empty($columns)) {
                $manager->createTable($tableName, $columns);
            }
            break;

        case 'exec_sql':
            // Execute arbitrary SQL
            if (empty($args)) {
                echo "Usage: exec_sql <SQL_statement>\n";
            } else {
                $manager->executeSql($args);
            }
            break;

        case 'query':
            // Execute SELECT query and display results
            if (empty($args)) {
                echo "Usage: query <SQL_statement>\n";
            } else {
                $results = $manager->query($args);
                if (!empty($results)) {
                    echo "--- Query Results ---\n";
                    // Get headers from the first row's keys
                    $headers = array_keys($results[0]);
                    echo implode("\t| ", $headers) . "\n";
                    echo str_repeat("-\t-", count($headers)) . "\n";
                    foreach ($results as $row) {
                        echo implode("\t| ", array_values($row)) . "\n";
                    }
                    echo "---------------------\n";
                } else {
                    echo "No results found or query failed.\n";
                }
            }
            break;

        case 'exit':
            echo "Exiting application. Goodbye!\n";
            exit(0); // Exit the script gracefully
            break;

        default:
            echo "Unknown command: '$command'. Type 'help' for available commands.\n";
            break;
    }
}

?>
