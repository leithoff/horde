<?php
/**
 * Token tracking implementation for PHP's PEAR database abstraction layer.
 *
 * Required parameters:<pre>
 *   'phptype'      The database type (ie. 'pgsql', 'mysql', etc.).</pre>
 *
 * Required by some database implementations:<pre>
 *   'database'     The name of the database.
 *   'hostspec'     The hostname of the database server.
 *   'username'     The username with which to connect to the database.
 *   'password'     The password associated with 'username'.
 *   'options'      Additional options to pass to the database.
 *   'tty'          The TTY on which to connect to the database.
 *   'port'         The port on which to connect to the database.</pre>
 *
 * Optional parameters:<pre>
 *   'table'        The name of the tokens table in 'database'.
 *                  Defaults to 'horde_tokens'.
 *   'timeout'      The period (in seconds) after which an id is purged.
 *                  Defaults to 86400 (i.e. 24 hours).</pre>
 *
 * Optional values when using separate reading and writing servers, for example
 * in replication settings:<pre>
 *   'splitread'   Boolean, whether to implement the separation or not.
 *   'read'        Array containing the parameters which are different for
 *                 the read database connection, currently supported
 *                 only 'hostspec' and 'port' parameters.</pre>
 *
 * The table structure for the tokens is as follows:
 *
 * <pre>
 * CREATE TABLE horde_tokens (
 *     token_address    VARCHAR(100) NOT NULL,
 *     token_id         VARCHAR(32) NOT NULL,
 *     token_timestamp  BIGINT NOT NULL,
 *
 *     PRIMARY KEY (token_address, token_id)
 * );
 * </pre>
 *
 * Copyright 1999-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Max Kalika <max@horde.org>
 * @package Horde_Token
 */
class Horde_Token_Sql extends Horde_Token
{
    /**
     * Handle for the current database connection.
     *
     * @var DB
     */
    protected $_db = '';

    /**
     * Handle for the current database connection, used for writing. Defaults
     * to the same handle as $_db if a separate write database is not required.
     *
     * @var DB
     */
    protected $_write_db;

    /**
     * Boolean indicating whether or not we're connected to the SQL
     * server.
     *
     * @var boolean
     */
    protected $_connected = false;

    /**
     * Constructs a new SQL connection object.
     *
     * @param array $params  A hash containing connection parameters.
     */
    protected function __construct($params = array())
    {
        parent::__construct($params);

        /* Set timeout to 24 hours if not specified. */
        if (!isset($this->_params['timeout'])) {
            $this->_params['timeout'] = 86400;
        }
    }

    /**
     * Deletes all expired connection id's from the SQL server.
     *
     * @throws Horde_Exception
     */
    public function purge()
    {
        $this->_connect();

        /* Build SQL query. */
        $query = 'DELETE FROM ' . $this->_params['table']
            . ' WHERE token_timestamp < ?';

        $values = array(time() - $this->_params['timeout']);

        /* Return an error if the update fails. */
        $result = $this->_write_db->query($query, $values);
        if ($result instanceof PEAR_Error) {
            if ($this->_logger) {
                $this->_logger->log($result, 'ERR');
            }
            throw new Horde_Exception_Prior($result);
        }
    }

    /**
     * TODO
     *
     * @return boolean  TODO
     */
    public function exists($tokenID)
    {
        try {
            $this->_connect();
        } catch (Horde_Exception $e) {
            return false;
        }

        /* Build SQL query. */
        $query = 'SELECT token_id FROM ' . $this->_params['table']
            . ' WHERE token_address = ? AND token_id = ?';

        $values = array($this->encodeRemoteAddress(), $tokenID);

        $result = $this->_db->getOne($query, $values);
        if ($result instanceof PEAR_Error) {
            if ($this->_logger) {
                $this->_logger->log($result, 'ERR');
            }
            return false;
        } else {
            return !empty($result);
        }
    }

    /**
     * TODO
     *
     * @throws Horde_Exception
     */
    public function add($tokenID)
    {
        $this->_connect();

        /* Build SQL query. */
        $query = 'INSERT INTO ' . $this->_params['table']
            . ' (token_address, token_id, token_timestamp)'
            . ' VALUES (?, ?, ?)';

        $values = array($this->encodeRemoteAddress(), $tokenID, time());

        $result = $this->_write_db->query($query, $values);
        if ($result instanceof PEAR_Error) {
            if ($this->_logger) {
                $this->_logger->log($result, 'ERR');
            }
            throw new Horde_Exception_Prior($result);
        }
    }

    /**
     * Opens a connection to the SQL server.
     *
     * @throws Horde_Exception
     */
    protected function _connect()
    {
        if ($this->_connected) {
            return;
        }

        Horde_Util::assertDriverConfig($this->_params, array('phptype'), 'token SQL');

        if (!isset($this->_params['database'])) {
            $this->_params['database'] = '';
        }
        if (!isset($this->_params['username'])) {
            $this->_params['username'] = '';
        }
        if (!isset($this->_params['password'])) {
            $this->_params['password'] = '';
        }
        if (!isset($this->_params['hostspec'])) {
            $this->_params['hostspec'] = '';
        }
        if (!isset($this->_params['table'])) {
            $this->_params['table'] = 'horde_tokens';
        }

        /* Connect to the SQL server using the supplied parameters. */
        $this->_write_db = DB::connect($this->_params,
                                 array('persistent' => !empty($this->_params['persistent']),
                                       'ssl' => !empty($this->_params['ssl'])));
        if ($this->_write_db instanceof PEAR_Error) {
            throw new Horde_Exception_Prior($this->_write_db);
        }

        // Set DB portability options.
        switch ($this->_write_db->phptype) {
        case 'mssql':
            $this->_write_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS | DB_PORTABILITY_RTRIM);
            break;

        default:
            $this->_write_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS);
            break;
        }

        /* Check if we need to set up the read DB connection
         * seperately. */
        if (!empty($this->_params['splitread'])) {
            $params = array_merge($this->_params, $this->_params['read']);
            $this->_db = DB::connect($params,
                                     array('persistent' => !empty($params['persistent']),
                                           'ssl' => !empty($params['ssl'])));
            if ($this->_db instanceof PEAR_Error) {
                throw new Horde_Exception_Prior($this->_db);
            }

            // Set DB portability options.
            switch ($this->_db->phptype) {
            case 'mssql':
                $this->_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS | DB_PORTABILITY_RTRIM);
                break;

            default:
                $this->_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS);
                break;
            }

        } else {
            /* Default to the same DB handle for read. */
            $this->_db = $this->_write_db;
        }

        $this->_connected = true;
    }

}
