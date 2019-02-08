<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  CSVUploads.TrialsData
 *
 * @copyright   Copyright (C) NPEU 2019.
 * @license     MIT License; see LICENSE.md
 */

defined('_JEXEC') or die;

/**
 * Save data to Trials database when the CSV is uploaded.
 */
class plgCSVUploadsTrialsData extends JPlugin
{
    protected $autoloadLanguage = true;
    
    protected $t_db;

    /**
     * Method to instantiate the indexer adapter.
     *
     * @param   object  &$subject  The object to observe.
     * @param   array   $config    An array that holds the plugin configuration.
     *
     */
    public function __construct(&$subject, $config)
    {

        parent::__construct($subject, $config);
        $this->loadLanguage();

        // The following file is excluded from the public git repository (.gitignore) to prevent
        // accidental exposure of database credentials. However, you will need to create that file
        // in the same directory as this file, and it should contain the follow credentials:
        // $database = '[A]';
        // $hostname = '[B]';
        // $username = '[C]';
        // $password = '[D]';
        //
        // if you prefer to store these elsewhere, then the database_credentials.php can instead
        // require another file or indeed any other mechansim of retrieving the credentials, just so
        // long as those four variables are assigned.
        require_once('database_credentials.php');

        try {
            $this->t_db = new PDO("mysql:host=$hostname;dbname=$database", $username, $password, array(
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8;'
            ));
        }
        catch(PDOException $e) {
            echo $e->getMessage();
            exit;
        }
    }
    
    /**
     * @param   array  $csv  Array holding data
     *
     * @return  boolean  True on success
     */
    public function onAfterLoadCSV($csv, $filename)
    {
        if ($filename != 'Trials-Data.csv') {
            
        }
        
        $sql = 'SELECT id FROM trials_data';
		
		$stmt = $this->t_db->prepare($sql);
		$stmt->execute();

		$ids  = array();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach($rows as $row) {
			$ids[] = $row['id'];
		}

		// Remove first row as it's heading names:
		#array_shift( $csv );
		$sql = array();
		foreach ($csv as  $row) {

			$rec_end   = $this->clean_year($row['Rec. end']);
			$grant_end = $this->clean_year($row['Grant end']);
			$any_end   = 'Null';
			if (is_numeric($rec_end) && is_numeric($grant_end)) {
				$any_end = max((int) $rec_end, (int) $grant_end);
			} else {
				if ($rec_end == 'Null') {
					$any_end = $grant_end;
				} else {
					$any_end = $rec_end;
				}
			}
			
			$data = array(
				'id'                  => $this->clean($row['ID']),
				'title'               => $this->clean($row['Title']),
				'long_title'          => $this->clean($row['Long Title']),
				'status'              => $this->html_id(preg_replace('/\d/', '', $row['Status'])),
				'status_full'         => $this->clean($row['Status']),
				'supported_trial'     => $this->clean_yn($row['Supported Trial'], 'N'),
				'support_role'        => $this->clean($row['Support Role']),
				'follow-up-only'      => $this->clean_yn($row['Follow-up only'], 'N'),
				'multi-single'        => $this->clean($row['Multi/single']),
				'funder'              => $this->clean($row['Funder']),
				'obstetric-neonatal'  => $this->clean($row['Obstetric / Neonatal']),
				'any_start'           => $this->clean_year($row['Any start']),
				'rec_start'           => $this->clean_year($row['Rec. start']),
				'rec_start_note'      => $this->clean($row['Rec. end note']),
				'rec_end'             => $rec_end,
				'rec_end_note'        => $this->clean($row['Rec. end note']),
				'rec_target'          => $this->clean($row['Rec. target']),
				'rec_total'           => $this->clean($row['Rec. total']),
				'rec_note'            => $this->clean($row['Rec. note']),
				'grant_start'         => $this->clean($row['GRANT START DATE']),
				'grant_start_note'    => $this->clean($row['Grant start note']),
				'grant_end'           => $grant_end,
				'grant_end_note'      => $this->clean($row['Grant end note']),
				'any_end'             => $any_end,
				'protocol_year'       => $this->clean($row['Protocol year']),
				'protocol_year_note'  => $this->clean($row['Protocol year note']),
				'publications'        => $this->clean($row['Publications']),
				'published_protocol'  => $this->clean($row['Published protocol']),
				'initial_source'      => $this->clean($row['Initial source']),
				'web_include'         => $this->clean_yn($row['Web include'], 'Y'),
				'web_landing_include' => $this->clean_yn($row['Landing include'], 'Y'),
				'web_home'            => $this->clean($row['Web alias']),
				'alias'               => isset($row['Web alias']) ? $this->clean($row['Web alias']) : $this->html_id($row['Title'], 'Y'),
				'eudract'             => $this->clean($row['EudraCT No.']),
				'rec_ref'             => $this->clean($row['REC Reference']),
				'isrctn'              => $this->clean($row['ISRCTN']),
				'ctu'                 => $this->clean($row['Clinical Trials Unit']),
				'sponser'             => $this->clean($row['Sponsor']),
				'duration'            => $this->clean($row['Duration of study']),
				'logo_alt'            => $this->clean($row['Logo alt text'])
            );

			if (in_array($row['ID'], $ids)) {
				// Update
				$id = $row['ID'];
				unset($data['id']);

				array_walk($data, function(&$value, $key){
					$value = '`' . $key . "`=" . $value;
				});
				$sql[] = 'UPDATE `trials_data` SET ' . implode(',', $data) . ' WHERE id = ' . $id . ";";
			} else {
				//Insert
				$sql[] = 'INSERT INTO `trials_data` (`' . implode('`,`', array_keys($data)). '`) VALUES (' . implode(",", $data) . ');';
			}
        }
		$sql = implode("\n", $sql);
        
		// Take Nulls out of quotes:
		$sql  = str_replace("'Null'", "Null", $sql);
		$this->t_db->query($sql);
        
        try {
			$this->t_db->query($sql);
		}
		catch(PDOException $e) {
			echo $e->getMessage();
			exit;
		}
        
        return true;
    }
    
    /**
     * Creates an HTML-friendly string for use in id's
     *
     * @param string $text
     * @return string
     * @access public
     */
    public function html_id($text)
	{
    	return "'" . strtolower(preg_replace('/\s+/', '-', trim(preg_replace('/[^a-zA-z0-9-_\s]/', '', $text)))) . "'";
	}
	
    /**
     * Cleans text.
     *
     * @param string $text
     * @return string
     * @access public
     */
	public function clean($text)
	{
    	return "'" . trim(str_replace("'", "\'", $text)) . "'";
	}

    /**
     * Creates year values
     *
     * @param string $text
     * @return string
     * @access public
     */
	public function clean_year($text)
	{
    	$text = trim($this->clean($text), "'");
		if (empty($text)) {
			$text = 'Null';
		}
		return $text;
	}
	
    /**
     * Cleans yes/no values
     *
     * @param string $text
     * @param string $default
     * @return string
     * @access public
     */
	public function clean_yn($text, $default)
	{
		#echo "1<pre>"; var_dump( $text ); echo "</pre>";
		$text = strtoupper(trim($this->clean($text), "'"));
		#echo "2<pre>"; var_dump( $text ); echo "</pre>";
    	$text = empty($text) ? $default : $text;
		#echo "3<pre>"; var_dump( $text ); echo "</pre>";
    	return "'" . $text . "'";
	}
}