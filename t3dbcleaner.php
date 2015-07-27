<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Christoph Schwob <christoph@websailor.at>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/


/**
 * Configuration for MySQL server
 */
define('HOST', 'localhost');
define('USERNAME', 'christoph');
define('PASSWORD', 'joh316');
define('DATABASE', 'db4711');

class InfoException extends Exception
{ 
}

class DangerException extends Exception
{ 
}

class SuccessException extends Exception
{ 
}
    
class t3dbcleaner 
{

    /**
     * @var array with table names
     */
    private $affectedTables = array();
    
    /**
     * @var array with records
     */
    private $affectedRecords = array();
    
    /**
     * Open PDO connection for further use
     *
     * @return PDO database handler
     * @throws DangerException
     */
    private function getConnection() 
    {
        try {
            $dbh = new PDO("mysql:host=" . HOST . ";dbname=" . DATABASE, USERNAME, PASSWORD);
            $dbh->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        } catch (PDOException $e) {
            throw new DangerException("Es konnte keine Verbindung mit der Datenbank hergestellt werden.");
        }
        return $dbh;
    }
    
    /**
     * Get count of records
     *
     * @return int records counter
     */
    public function getRecordsCounter() 
    {
        return count( $this->affectedRecords );
    }

    /**
     * Get count of tables
     *
     * @return int tables counter
     */
    public function getTablesCounter() 
    {
        return count( $this->affectedTables );
    }
    
    /**
     * Get array of all tables in selected database
     *
     * @return array with all tablenames
     */
    private function getTables() 
    {
        $dbh = $this->getConnection();
        $result = $dbh->query( "SHOW tables;" );
        while ($row = $result->fetch()) {
            $tables[] = $row[0];
        }
        return $tables;
    }
    
    /**
     * Search for tables with 'deleted' columns
     *
     * @return void
     * @throws InfoException
     */
    private function getAffectedTables() 
    {
        $dbh = $this->getConnection();
        $tables = $this->getTables();
        foreach ($tables as $table) {
            $result = $dbh->query( "SHOW COLUMNS FROM $table WHERE field = 'deleted';" );
            if ($result->rowCount() > 0) {
                while($row = $result->fetch()) {
                    $this->affectedTables[] = $table;
                }
            }
        }
        $dbh = null;
        if (count($this->affectedTables) == 0) {
            throw new InfoException("Es wurden keine Tabellen mit gelöschten Datensätzen gefunden.");
        }
    }
    
    /**
     * Search for deleted records
     *
     * @return void
     * @throws SuccessException
     */
    private function getAffectedRecords() 
    {
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
        if (count($this->affectedRecords) == 0) {
            throw new SuccessException("Es wurden keine gelöschten Datensätze gefunden!");
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
        $message = 'Es wurden ' . $this->getRecordsCounter() . ' gelöschte Datensätze gefunden.';
        $return = '<div class="alert alert-info" role="alert"><b>Info!</b>' . $message . '</div>';
        echo $return . $table;
    }
    
    /**
     * Starts the TYPO3 Database Cleaner
     *
     * @return void
     */
    public function start() 
    {
        $this->getAffectedTables();
        $this->getAffectedRecords();
    }
    
    /**
     * deletes the flagged records forever
     *
     * @return void
     * @throws DangerException
     */
    public function deleteRecords()
    {
        $counter = 0;
        $dbh = $this->getConnection();
        for ($i = 1; $i < $this->getRecordsCounter() + 1; $i++) {
            $table = $this->affectedRecords[$i]['table'];
            $a = $dbh->exec("DELETE FROM $table WHERE deleted=1 LIMIT 1;");
            if ( $a == 1 ) {
                $counter++;
            } else {
                throw new DangerException("Ein oder mehrere Datensätze konnten nicht gelöscht werden.");
            }
        }
        $dbh = null;
        if ( $counter == $this->getRecordsCounter() ) {
            $message = $counter . ' Datensätze wurden entgültig gelöscht.';
            $return = '<div class="alert alert-success" role="alert"><b>Glückwunsch! </b>' . $message . '</div>';
        }
        echo $return;
    }
}

?>

<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <title>TYPO3 Database Cleaner</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" rel="stylesheet">
    <link href="http://typo3.org/typo3conf/ext/t3org_template/icons/favicon.ico" rel="icon" type="image/x-icon" />
    <style>
        #container {
            max-width: 800px;
            margin: 45px auto;
        }
        .typo3-logo {
            padding-top: 20px;
        }
        .alert {
            padding: 20px;
            margin: 30px 0;
        }
        .alert b {
            margin-right: 10px;
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
</head>

<body>
    <div id="container">
        <div class="row">
            <div class="col-md-10">
                <h1>TYPO3 Database Cleaner <small>3.0</small></h1>
            </div>
            <div class="col-md-2 typo3-logo">
                <img src="http://typo3.org/typo3conf/ext/t3org_template/i/typo3-logo.png" alt="TYPO3 Logo" />
            </div>
        </div>
<?php
    
try {
    $t3dbc = new t3dbcleaner();
    $t3dbc->start();
    if ( isset($_GET['action']) && $_GET['action'] == 'delete' ) {
        $t3dbc->deleteRecords();
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
    
} catch (DangerException $e) {
    echo '<div class="alert alert-danger" role="alert"><b>Fehler!</b>' . $e->getMessage() . '</div>';
} catch (InfoException $e) {
    echo '<div class="alert alert-info" role="alert"><b>Info!</b>' . $e->getMessage() . '</div>';
} catch (SuccessException $e) {
    echo '<div class="alert alert-success" role="alert"><b>Glückwunsch!</b>' . $e->getMessage() . '</div>';
}
    
?>
        <p class="copyright">
            <b>TYPO3 Database Cleaner</b> ist ein Projekt von <a href="http://websailor.at" target="_blank">Christoph Schwob</a>.
            <span class="pull-right">&copy; 2015</span>
        </p>
    </div>
</body>
</html>