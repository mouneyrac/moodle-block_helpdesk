<?php
/**
 * Abstract helpdesk class. This defines the layout that a helpdesk plugin must
 * have and sets a layout and structure for the helpdesk.
 *
 * @copyright   2010 VLACS
 * @author      Jonathan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package     block_helpdesk
 */
abstract class helpdesk {

    /**
     * Every helpdesk has access to the moodle cron for this block. This method 
     * gets called every time cron hits the block.
     *
     * @return true
     */
    abstract function cron();

    /**
     * This method can be overridden to run tasks after the tables have been
     * created on install.
     *
     * @return bool
     */
    function install() {
        return true;
    }

    /**
     * Depending on the helpdesk being used, we want to check to see if an
     * update is hidden or not.
     *
     * @param object    $update that may be hidden.
     * @return bool
     */
    abstract function is_update_hidden($update);

    /**
     * returns a new ticket object.
     *
     * @return object
     */
    abstract function new_ticket();

    /**
     * gets a specific ticket by a unique id. Returns false
     * if none are found, or a ticket object of the ticket with the given id.
     *
     * @param mixed $id Ticket id to be fetched which is either a string or int.
     * @return mixed
     */
    abstract function get_ticket($id);

    /**
     * Abstract search function that either returns a false is the search is
     * empty or an array of ticket objects that the search turned up.
     *
     * @param string     $string Search string.
     * @return mixed
     */
    abstract function search($data, $count=10, $page=0);    # todo: check to see if we should change this

    /**
     * Abstract methods that returns a new ticket form for the helpdesk's
     * respective plugin.
     *
     * @param array     $data is an array of stuff to be used by the plugin,
     *                  such as new ticket tags.
     * @return moodleform
     */
    abstract function new_ticket_form($data=null);

    /**
     * Returns a moodleform object.
     *
     * @param array     $ticket This form takes in a ticket object.
     * @return mixed
     */
    abstract function update_ticket_form($ticket);

    /**
     * creates and returns a moodleform object for adding a tag
     * to the ticket object passed to it.
     *
     * @param object    $ticketid ID of the ticket that a tag is being added to.
     * @return object
     */
    abstract function tag_ticket_form($ticketid);

    /**
     * create and returns a moodleform object for searching
     * tickets.
     *
     * @return object
     */
    abstract function search_form();

    /**
     * Generates a form for changing ticket overview details.
     *
     * @return object
     */
    abstract function change_overview_form($ticket);

    /**
     * Unique to Moodle 2.x. This is one of the first things we do on any page
     * and that is configure the $PAGE. We don't want to output the header yet
     * because there could be redirections that will rely on the set URL in this
     * method. --jdoane 20121105
     */
    public static function page_init($title, $nav=array(), $url=null) {
        global $PAGE;
        // Set up the page
        $PAGE->set_context(context_system::instance());
        $PAGE->set_heading($title);
        $PAGE->set_title($title);
        $PAGE->set_url(isset($url) ? $url : qualified_me());
        $PAGE->requires->css('/blocks/helpdesk/style.css');

        // Set up navigation, there are a couple different things we can do
        // here. :)
        $crumb_nav =& $PAGE->navbar;
        foreach($nav as $navitem) {
            if(empty($navitem['link'])) {
                $navitem['link'] = null;
            }
            $crumb_nav->add($navitem['name'], $navitem['link']);
        }

        // We also have this neat navigation inside the navigation block which
        // is configured as a tree, we will want to use this to provide some
        // "easy to access" help desk links that are not related to the Help
        // Desk context.
        $hd_nav = $PAGE->navigation->add(get_string('helpdesk', 'block_helpdesk'));

        // TODO: Add some more cool stuff here. --jdoane 20121105
    }

    /**
     * A factory function to return a constructed helpdesk from a selected plugin
     * in the configuration of the helpdesk.
     *
     * @return object
     */
    public final static function get_helpdesk() {
        global $CFG;
        $plugin = 'native';
        if(isset($CFG->helpdesk_plugin) and strlen($CFG->helpdesk_plugin) > 0) {
            $plugin = $CFG->helpdesk_plugin;
        }
        $class = "helpdesk_$plugin";

        $initpath = "{$CFG->dirroot}/blocks/helpdesk/plugins/$plugin/init.php";

        if (!file_exists($initpath)){
            print_error('missingpluginfile', 'helpdesk_block');
        }

        require_once($initpath);

        return new $class;
    }

    public function display_ticket($ticket, $readonly=false) {
        if (method_exists($ticket, 'fetch')) {
            return $ticket->display_ticket($readonly);
        }
        return false;
    }

    // Relation methods start here!

    /**
     * Gets ticket relations for a specific plugin and capability.
     */
    abstract function get_ticket_relations($cap);

    /**
     * Gets the default relation for a specified plugin.
     *
     * @return string
     */
    abstract function get_default_relation($cap=null);

    /**
     * Gets the search parameters for a legacy relation.
     * TODO: Replace relation system all togther.
     */
    abstract function get_ticket_relation_search($rel);

    /**
     * Gets a language string from a relation string.
     *
     * @param string    $rel ation string to convert to a human readable string.
     * @return string
     */
    abstract function get_relation_string($rel);

    /**
     * Get the default URL to submit a ticket for this plugin.
     * Plugins can use this to collect available context data in the ticket by
     * default, without requiring user participation on those points.
     *
     * Returns a moodle_url object for the 'submitnewticket' link in the block.
     *
     * @return object
     */
    abstract function default_submit_url();

    /**
     * Gets status ids for the given parameters.
     *
     * @return array
     */
    abstract function get_status_ids($active=true, $inactive=true, $core=false);

    /**
     * Creates a new stdClass object with the approriate attributes.
     *
     * @return object
     */
    function new_search_obj() {
        return (object)array(
            'searchstring' => '',
            'answerer' => -1,
            'status' => array(),
            'submitter' => 0,
            'watcher' => 0,
        );
    }
}
