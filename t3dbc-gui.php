<?php
session_start();

if ( isset($_POST['action']) && $_POST['action'] == "connect" )
{
    $_SESSION['CONFIG'] = $_POST;
    //$_SESSION['CONFIG']['action'] = "";
    header('Location: ' . $_SERVER['PHP_SELF'] . '?action=show');
    exit;
}

if ( isset($_SESSION['CONFIG']) ) {
    $_POST = $_SESSION['CONFIG'];
    unset($_POST['action']);
    session_unset();
    session_destroy();
}
    
$PDODrivers = [
    "cubrid" => "Cubrid",
    "dblib" => "FreeTDS / Microsoft SQL Server / Sybase",
    "firebird" => "Firebird",
    "ibm" => "IBM DB2",
    "informix" => "IBM Informix Dynamic Server",
    "mysql" => "MySQL",
    "oci" => "Oracle Call Interface",
    "odbc" => "ODBC v3 (IBM DB2, unixODBC und win32 ODBC)",
    "pgsql" => "PostgreSQL",
    "sqlite" => "SQLite",
    "sqlsrv" => "Microsoft SQL Server / SQL Azure"
];

class InfoException extends Exception { }
class DangerException extends Exception { }
    
class t3dbcleaner {
    
    private $affectedTables = array();
    private $affectedRecords = array();
    
    /**
	 * Open PDO connection for further use
	 *
	 * @return PDO databasehandler
	 */
    private function getConnection() {
        try {
            $connectionstring = $_POST['driver'] . ':host=' . $_POST['host'] . ';dbname=' . $_POST['database'];
            $DBH = new PDO($connectionstring, $_POST['username'], $_POST['password']);  
            $DBH->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        }
        catch ( PDOException $e ) {
            throw new DangerException("Es konnte keine Verbindung mit der Datenbank hergestellt werden.");
        }
        return $DBH;
    }
    
    /**
	 * Get count of records
	 *
	 * @return int records counter
	 */
    public function getRecordsCounter() {
        return count($this->affectedRecords);
    }

    /**
	 * Get count of tables
	 *
	 * @return int tables counter
	 */
    public function getTablesCounter() {
        return count($this->affectedTables);
    }
    
    
    /**
	 * Get array of all tables in selected database
	 *
	 * @return array<String> tablenames
	 */
    private function getTables() {
        $dbh = $this->getConnection();
        $result = $dbh->query("SHOW tables;");
        while( $row = $result->fetch() ) {
            $tables[] = $row[0];
        }
        return $tables;
    }
    
    
    /**
	 * Search for tables with 'deleted' columns
	 *
	 * @return void
	 */
    private function getAffectedTables() {
        $dbh = $this->getConnection();
        $tables = $this->getTables();
        foreach($tables as $table) {
            $result = $dbh->query("SHOW COLUMNS FROM $table WHERE field = 'deleted';");
            if ($result->rowCount() > 0) {
                while($row = $result->fetch()) {
                    $this->affectedTables[] = $table;
                }
            }
        }
        $dbh = null;
        if ( count($this->affectedTables) == 0 ) {
            throw new InfoException("Es wurden keine Tabellen mit gelöschten Datensätzen gefunden.");
        }
    }
    
    /**
	 * Search for deleted records
	 *
	 * @return void
	 */
    private function getAffectedRecords() {
        $i = 0;
        $dbh = $this->getConnection();
        foreach ($this->affectedTables as $value) {
            $result = $dbh->query( "SELECT * FROM $value WHERE deleted=1;" );
            $result->setFetchMode(PDO::FETCH_ASSOC);
            if ($result->rowCount() > 0) {
                while ($row = $result->fetch()) {
                    $i++;
                    $this->affectedRecords[$i] = array();
                    if (!isset($row['pid'])) {
                        $this->affectedRecords[$i]['pid'] = "-";
                    } else {
                        $this->affectedRecords[$i]['pid'] = $row['pid'];
                    }
                    $this->affectedRecords[$i]['table'] = $value;
                }
            }
        }
        $dbh = null;
        if ( count($this->affectedRecords) == 0 ) {
            throw new InfoException("Es wurden keine gelöschten Datensätze gefunden!");
        }
    }

    /**
	 * Prints message and records table
	 *
	 * @return void
	 */
    public function showRecords()
    {
        $counter = 0;
        $table = "<table id='records' class='table table-striped'>";
        $table .= "<thead><tr><th>#</th><th>PID</th><th>Database Table</th></tr></thead>" . "<tbody>";
        foreach( $this->affectedRecords as $value ) {
            $counter++;
            $table .= "<tr><td>" . $counter . "</td><td>";
            $table .= $value['pid'];
            $table .= '</td><td>' . $value['table'] . '</td></tr>';
        }
        $table .= "</tbody></table>";
        $message = 'Es wurden ' . $this->getRecordsCounter() . ' gelöschte Datensätze in ' 
            . $this->getTablesCounter() . ' Tabellen gefunden!';
        $alert = '<div class="alert alert-info" role="alert"><b>Info! </b>' . $message . '</div>';
        echo $alert . $table;
    }
    
    /**
	 * Starts the TYPO3 Database Cleaner
	 *
	 * @return void
	 */
    public function start() {
        $this->getAffectedTables();
        $this->getAffectedRecords();
    }
    
    /**
	 * deletes the flagged records forever
	 *
	 * @return void
	 */
    public function deleteRecords() {
        $temp = $this->affectedRecords;
        $anz = 0;
        $mysqli = $this->getConnection();
        for($i = 1; $i < $this->RecordsCounter() + 1; $i++) {
            $table = $this->affectedRecords[$i]['table'];
            $mysqli->query("DELETE FROM $table WHERE deleted=1 LIMIT 1;");
            if ( $mysqli->affected_rows == 1 ) {
                $anz++;
            }
            else
                throw new DangerException("Ein oder mehrere Datensätze konnten nicht gelöscht werden.");
        }
        $mysqli->close();
        if ( $anz != $this->RecordsCounter() ) {
            $message = 'Es konnten nur $anz aller Datensätze gelöscht werden!';
            $return = '<div class="alert alert-warning" role="alert"><b>Info! </b>' . $message . '</div>';
        } else {
            $message = 'Folgende ' . count($temp) . ' Datensätze wurden entgültig gelöscht!';
            $return = '<div class="alert alert-success" role="alert"><b>Info! </b>' . $message . '</div>';
        }
        echo $return . $this->showRecordsArray( $temp );
    }
}

print_r($_POST);

?>

<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" rel="stylesheet">
<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet">

<style>
#container {
	max-width: 960px;
	margin: 20px auto;
	border: 1px solid #CCC;
	border-radius: 5px;
	box-sizing: border-box;
	padding: 0 20px 20px 20px;
}
            .alert {
            padding: 20px;
            margin: 30px 0;
        }
        button#btn-print {
            margin-right: 4px;
        }
        p.copyright {
            border-top: 1px solid #ccc;
            margin: 30px 0 0 0;
            padding: 15px 0;
        }
</style>

<div id="container">
	<div class="row">
		<div class="col-md-12">
	  		<h1>TYPO3 Database Cleaner <small>3.0</small></h1>
            <span class="pull-right"><i class="fa fa-cog"></i></span>
        </div>
    </div>
    <div class="row">
        
        <!-- FORM -->
        <form class="form-horizontal col-sm-7" action="<?= $_SERVER['PHP_SELF']; ?>" method="post">
            <div class="form-group">
                <label for="selectDriver" class="col-sm-5 control-label">Select PDO Driver:</label>
                <div class="col-sm-7">
                    <input type="hidden" name="driver" value="mysql" />
                    <select name="driver" class="form-control" id="selectDriver" >
                        <?php 
                        foreach (PDO::getAvailableDrivers() as $driver) {
                            echo '<option value="' . $driver . '">' . $PDODrivers[$driver] . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="inputHost" class="col-sm-5 control-label">Database Host:</label>
                <div class="col-sm-7">
                    <input type="text" name="host" class="form-control" id="inputHost" 
                           placeholder="e.g. localhost" value="<?php echo isset($_POST['host']) ? $_POST['host'] : ''; ?>" />
                </div>
            </div>
            <div class="form-group">
                <label for="inputUsername" class="col-sm-5 control-label">Database User:</label>
                <div class="col-sm-7">
                    <input type="text" name="username" class="form-control" id="inputUsername" 
                           value="<?php echo isset($_POST['username']) ? $_POST['username'] : ''; ?>" />
                </div>
            </div>
            <div class="form-group">
                <label for="inputPassword" class="col-sm-5 control-label">Database Password:</label>
                <div class="col-sm-7">
                    <input type="password" name="password" class="form-control" id="inputPassword" 
                           value="<?php echo isset($_POST['password']) ? $_POST['password'] : ''; ?>" />
                </div>
            </div>
            <div class="form-group">
                <label for="inputDatabase" class="col-sm-5 control-label">Database:</label>
                <div class="col-sm-7">
                    <input type="text" name="database" class="form-control" id="inputDatabase" 
                           value="<?php echo isset($_POST['database']) ? $_POST['database'] : ''; ?>" />
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-5 col-sm-7">
                    <button type="submit" class="btn btn-default" name="action" value="connect" >Connect to Database</button>
                </div>
            </div>
        </form>
        <!-- FORM -->
        
    </div>
        
<?php
    
try {
    $t3dbc = new t3dbcleaner();
    $t3dbc->start();
    if ( isset($_GET['action']) && $_GET['action'] == 'delete' ) {
        $t3dbc->deleteRecords();

?>

    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
        <button type="button" id="btn-cancel" onClick="javascript:window.close()" class="btn btn-danger">Abbrechen</button>
        <button type="submit" id="btn-delete" class="btn btn-success pull-right" value="delete" 
                name="action" disabled="disabled">Löschen</button>
        <button type="button" id="btn-print" onClick="javascript:window.print()" class="btn btn-primary pull-right">Drucken</button>
    </form>

<?php
    
    } else {
        $t3dbc->showRecords();

?>
    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
        <button type="button" id="btn-cancel" onClick="javascript:window.close()" class="btn btn-danger">Abbrechen</button>
        <button type="submit" id="btn-delete" class="btn btn-success pull-right" value="delete" name="action">Löschen</button>
        <button type="button" id="btn-print" onClick="javascript:window.print()" class="btn btn-primary pull-right">Drucken</button>
    </form>

<?php
            }

        }
        catch ( DangerException $e ) {
            echo '<div class="alert alert-danger" role="alert"><b>Fehler! </b>' . $e->getMessage() . '</div>';
        }
        catch ( InfoException $e ) {
            echo '<div class="alert alert-info" role="alert"><b>Info! </b>' . $e->getMessage() . '</div>';
        }
            
    ?>
    
    
    <p class="copyright">
        <b>TYPO3 Database Cleaner</b> ist ein Projekt von <a href="http://websailor.at" target="_blank">Christoph Schwob</a>.
        <span class="pull-right">&copy; 2014</span>
    </p>