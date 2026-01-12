<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  CSVUploads.TrialsData
 *
 * @copyright   Copyright (C) NPEU 2024.
 * @license     MIT License; see LICENSE.md
 */

namespace NPEU\Plugin\CSVUploads\TrialsData\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * Save data to Trials database when the CSV is uploaded.
 */
class TrialsData extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    protected $t_db;

    protected $j_date_format = 'Y-m-d H:i:s';

    /**
     * An internal flag whether plugin should listen any event.
     *
     * @var bool
     *
     * @since   4.3.0
     */
    protected static $enabled = false;

    /**
     * Constructor
     *
     */
    public function __construct($subject, array $config = [], bool $enabled = true)
    {
        // The above enabled parameter was taken from the Guided Tour plugin but it always seems
        // to be false so I'm not sure where this param is passed from. Overriding it for now.
        $enabled = true;


        #$this->loadLanguage();
        $this->autoloadLanguage = $enabled;
        self::$enabled          = $enabled;

        parent::__construct($subject, $config);

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
        /*require_once(realpath(dirname(dirname(__DIR__))) . '/database_credentials.php');

        try {
            $db = new \PDO("mysql:host=$hostname;dbname=$database", $username, $password, [
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8;'
            ]);
        }
        catch(\PDOException $e) {
            echo $e->getMessage();
            exit;
        }*/
    }

    /**
     * function for getSubscribedEvents : new Joomla 4 feature
     *
     * @return array
     *
     * @since   4.3.0
     */
    public static function getSubscribedEvents(): array
    {
        return self::$enabled ? [
            'onAfterLoadCSV' => 'onAfterLoadCSV',
        ] : [];
    }

    /**
     * @param   array  $csv  Array holding data
     *
     * @return  string 'STOP'
     */
    public function onAfterLoadCSV(Event $event): string
    {
        [$csv, $filename] = array_values($event->getArguments());

        if ($filename != 'trials-data.csv') {
            return false;
        }

        $user_id = Factory::getApplication()->getIdentity()->get('id');

        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->select($db->quoteName('id'))
              ->from($db->quoteName('#__trials'));

        $db->setQuery($query);
        $rows = $db->loadAssocList();

        $ids  = [];
        foreach($rows as $row) {
            $ids[] = $row['id'];
        }

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

            $data = (object) [
                'id'                  => $row['ID'],
                'title'               => $this->clean($row['Title']),
                'alias'               => !empty($row['Web alias']) ? $this->clean($row['Web alias']) : $this->html_id($row['Title']),
                'long_title'          => $this->clean($row['Long Title']),
                'descriptor'          => $this->clean($row['Descriptor']),
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
                'rec_total'           => $this->clean_int($row['Rec. total']),
                'rec_note'            => $this->clean($row['Rec. note']),
                'grant_start'         => $this->clean_date($row['GRANT START DATE']),
                'grant_start_note'    => $this->clean($row['Grant start note']),
                'grant_end'           => $grant_end,
                'grant_end_note'      => $this->clean($row['Grant end note']),
                'any_end'             => $any_end,
                'protocol_year'       => $this->clean_year($row['Protocol year']),
                'protocol_year_note'  => $this->clean($row['Protocol year note']),
                'publications'        => $this->clean($row['Publications']),
                'published_protocol'  => $this->clean($row['Published protocol']),
                'initial_source'      => $this->clean($row['Initial source']),
                'summary_of_results'  => $this->clean($row['Summary of Results']),
                'other_files'         => $this->clean($row['Other files']),
                'web_include'         => $this->clean_yn($row['Web include'], 'Y'),
                'web_landing_include' => $this->clean_yn($row['Landing include'], 'Y'),
                'web_home'            => $this->clean($row['Web alias']),
                'eudract'             => $this->clean($row['EudraCT No.']),
                'rec_ref'             => $this->clean($row['REC Reference']),
                'isrctn'              => $this->clean($row['ISRCTN']),
                'ctu'                 => $this->clean($row['Clinical Trials Unit']),
                'sponsor'             => $this->clean($row['Sponsor']),
                'controller'          => $this->clean($row['Data Controller']),
                'duration'            => $this->clean($row['Duration of study']),
                'logo_alt'            => $this->clean($row['Logo alt text']),
                'modified'            => $this->clean(date($this->j_date_format)),
                'modified_by'         => $user_id
            ];

            if (in_array($row['ID'], $ids)) {
                // Update:

                $result = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class)->updateObject('#__trials', $data, 'id', true);
            } else {
                //Insert:

                // Find brand id:
                $brand_query = $db->getQuery(true);

                // Select the required fields from the table.
                $brand_query->select('id')
                        ->from($db->quoteName('#__brands'))
                        ->where('alias = "' . $data->alias . '"');
                $db->setQuery($brand_query);
                if (!$db->execute($brand_query)) {
                    throw new GenericDataException($db->stderr(), 500);
                    return false;
                }

                $brand_id = $db->loadResult();
                if ($brand_id) {
                    $data->brand_id = $brand_id;
                }

                // Add standard values:
                $data->created    = $this->clean(date($this->j_date_format));
                $data->created_by = $user_id;

                $result = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class)->insertObject('#__trials', $data, 'id', true);
            }
        }

        return 'STOP';
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
        return strtolower(preg_replace('/\s+/', '-', trim(preg_replace('/[^a-zA-z0-9-_\s]/', '', $text))));
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
        return trim(str_replace("'", "\'", $text));
    }

    /**
     * Cleans integers.
     *
     * @param string $text
     * @return string|null
     * @access public
     */
    public function clean_int($int)
    {
        $int = trim(trim($this->clean($int), "'"));

        if (empty($int) || !is_int($int)) {
            $int = null;
        }
        return $int;
    }

    /**
     * Creates year values
     *
     * @param string $text
     * @return string|null
     * @access public
     */
    public function clean_date($text)
    {
        $text = trim(trim($this->clean($text), "'"));

        if (empty($text)) {
            $date = '0000-00-00';
        } elseif (strlen($text) == 4) {
            // Assume this must be a year
            $date = $text . '-00-00';
        } else {
            // If entry uses slashes, convert to dashes so strtotime uses UK date format:
            $date = date('Y-m-d', strtotime(str_replace('/', '-', $text)));
        }

        return $date;
    }

    /**
     * Creates year values
     *
     * @param string $text
     * @return string|null
     * @access public
     */
    public function clean_year($text)
    {
        $text = trim(trim($this->clean($text), "'"));
        if (empty($text)) {
            $text = null;
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
        $text = strtoupper(trim($this->clean($text), "'"));
        $text = empty($text) ? $default : $text;
        return $text;
    }
}