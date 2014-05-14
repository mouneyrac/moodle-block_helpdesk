<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Help Desk Ticket, Native
 *
 * Help desk ticket native is the ticket class that handles all
 * operations to an individual ticket.
 *
 * @package     block_helpdesk
 * @copyright   2010 VLACS
 * @author      Jonathan Doane <jdoane@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class helpdesk_ticket_native extends helpdesk_ticket {
    // Ticket db fields.
    public $id;
    public $summary;
    public $status;
    public $detail;
    public $timecreated;
    public $timemodified;
    public $hd_userid;
    public $createdby;
    public $firstcontact;

    // All child db tables that have a relation with this ticket object.
    public $tags;
    public $updates;
    public $users;

    /**
     * Constructor for native help desk ticket. This makes empty ticket with
     * some pre-initialized variables. This only gets called by the new_ticket
     * methods in the help desk and the ticket itself.
     *
     * @return null
     */
    function __construct() {
        $tags       = array();
        $updates    = array();
        $users      = array();
    }

    /**
     * Display ticket method that was recently moved to the plugin level. This
     * will allow plugins to customize how tickets are view depending on the
     * features for each plugin.
     *
     * @param object    $ticket is an already fetched ticket object with a valid
     *                  id.
     * @return bool
     */
    function display_ticket($readonly=false) {
        global $CFG, $USER, $DB, $OUTPUT;

        $hd = helpdesk::get_helpdesk();

        $this->fetch();

        $isanswerer = helpdesk_is_capable(HELPDESK_CAP_ANSWER);

        $udata = $this->get_updates($isanswerer);
        $tags  = $this->get_tags();

        $showfirstcontact = get_config(null, 'block_helpdesk_show_firstcontact');

        $user = helpdesk_get_hd_user($this->hd_userid);

        echo "<div class=\"ticketinfo\">";
        $overviewstr = get_string('ticketinfo', 'block_helpdesk');
        $overviewhelp = $OUTPUT->help_icon('ticketinfo', 'block_helpdesk');
        $editurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/edit.php");
        $editurl->param('id', $this->get_idstring());
        $editurl = $editurl->out();
        $editstr = get_string('editoverview', 'block_helpdesk');
        $headstr = "$overviewstr $overviewhelp";
        if (helpdesk_is_capable(HELPDESK_CAP_ANSWER) and !$readonly) {
            $headstr .= "<br /><a href=\"$editurl\">$editstr</a>";
        }

        $table = new html_table();
        $table->head = array($headstr);
        $table->headspan = array(2);
        $table->size = array('30%');
        $table->width = '95%';

        $row = array();
        $row[] = get_string('ticketid', 'block_helpdesk');
        $row[] = $this->get_idstring();
        $table->data[] = $row;

        $row = array();
        $str = get_string('user');
        if ($isanswerer and !$readonly) {
            $newuserurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/userlist.php");
            $newuserurl->param('function', HELPDESK_USERLIST_NEW_SUBMITTER);
            $newuserurl->param('tid', $this->get_idstring());
            $str .="<br /><small><a href=\"" . $newuserurl->out() . "\">" .
                   get_string('changeuser', 'block_helpdesk') . '</a></small>';
        }
        $row[] = $str;
        $row[] = helpdesk_user_link($user);
        $table->data[] = $row;

        $createdby = helpdesk_get_hd_user($this->createdby);
        $row = array();
        $row[] = get_string('submittedby', 'block_helpdesk');
        $row[] = helpdesk_user_link($createdby);
        $table->data[] = $row;

        if ($this->firstcontact != null and $showfirstcontact != false) {
            $help = $OUTPUT->help_icon('firstcontact', 'block_helpdesk');

            $row = array();
            $row[] = get_string('firstcontactuser', 'block_helpdesk') . $help;
            $row[] = helpdesk_user_link($this->firstcontact);
            $table->data[] = $row;
        }

        $row = array();
        $row[] = get_string('timecreated', 'block_helpdesk');
        $row[] = helpdesk_get_date_string($this->get_timecreated());
        $table->data[] = $row;

        $row = array();
        $row[] = get_string('timemodified', 'block_helpdesk');
        $row[] = helpdesk_get_date_string($this->get_timemodified());
        $table->data[] = $row;

        $row = array();
        $row[] = get_string('status', 'block_helpdesk');
        $status = $this->get_status();
        if ($status->core == true and empty($status->displayname)) {
            $row[] = get_string($status->name, 'block_helpdesk');
        } else {
            $row[] = $status->displayname;
        }
        $table->data[] = $row;

        $row = array();
        $row[] = get_string('summary', 'block_helpdesk');
        $row[] = $this->get_summary();
        $table->data[] = $row;

        $row = array();
        $row[] = get_string('detail', 'block_helpdesk');
        $row[] = file_rewrite_pluginfile_urls($this->get_detail(), 'pluginfile.php',
            context_system::instance()->id, 'block_helpdesk', 'ticketdetail', $this->get_idstring());
        $table->data[] = $row;

        /**
         * TODO: Expand on this.
         * If we want a series of tool we will want to put them here.
         */
        if(empty($this->firstcontact) and helpdesk_is_capable(HELPDESK_CAP_ANSWER)) {
            $row = array();
            $row[] = get_string('answerertools', 'block_helpdesk');
            $url = "{$CFG->wwwroot}/blocks/helpdesk/plugins/native/action/grab.php";
            $grab_help = $OUTPUT->help_icon('grabquestion', 'block_helpdesk');
            $row[] = "<form action=\"{$url}\" method=\"get\">"
                . '<input type="hidden" name="id" value="'
                . $this->get_idstring() . '" />'
                . '<input type="submit" value="'
                . get_string('grabquestion', 'block_helpdesk')
                . "\" />{$grab_help}</form>";
            $table->data[] = $row;
        }

        echo html_writer::table($table, false);
        echo '<br />';

        // Assignments start here.
        $assignedstr = get_string('assignedusers', 'block_helpdesk');
        $assignedhelp = $OUTPUT->help_icon('assignedusers', 'block_helpdesk');
        $thead = $assignedstr . $assignedhelp;

        // If the user is a answerer, he can assign people to the ticket.
        if($isanswerer and !$readonly) {
            $url = new moodle_url("$CFG->wwwroot/blocks/helpdesk/assign.php");
            $url->param('tid', $this->get_idstring());
            $url = $url->out();
            $string = get_string("assignuser", 'block_helpdesk');
            $thead .= "<br /><a href=\"$url\">$string</a>";
        }
        $table->head = array($thead);
        $table->headspan = array(2);

        $assigned = $this->get_assigned();

        if ($assigned === false) {
            $table->data = array(array(get_string('noneassigned', 'block_helpdesk')));
        } else {
            $table->data = array();

            foreach($assigned as $user) {
                $removeurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/assign.php");
                $removeurl->param('remove', 'true');
                $removeurl->param('uid', $user->userid);
                $removeurl->param('tid', $this->get_idstring());
                $removeurl = $removeurl->out();

                $row = array();
                $row[] = helpdesk_user_link($user);
                if ($isanswerer and !$readonly) {
                    $row[] = "<a href=\"$removeurl\">" . get_string('remove') . "</a>";
                }
                $table->data[] = $row;
            }
        }
        $table->size = array('70%');
        echo html_writer::table($table, false);
        echo '<br />';

        // Assignments end here.


        // WATCHERS
        // todo: make sure this section works in 1.9

        if ($isanswerer) {
            $watcherstr = get_string('watchingusers', 'block_helpdesk');
            $whead = $watcherstr . $OUTPUT->help_icon('watchingusers', 'block_helpdesk');
            if (!$readonly) {
                $addurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/userlist.php");
                $addurl->param('function', HELPDESK_USERLIST_NEW_WATCHER);
                $addurl->param('tid', $this->get_idstring());
                $whead .= '<br /><a href="' . $addurl->out() . '">' . get_string('assignwatchers', 'block_helpdesk') . '</a>';
            }
            $table->head = array($whead);
            $table->headspan = array(2);

            //$watcher_table->class = 'generaltable helpdesktable watchertable';

            if (!$watchers = $this->get_watchers()) {
                $table->data = array(array(get_string('nowatchers', 'block_helpdesk')));
            } else {
                $removeurl = new moodle_url("$CFG->wwwroot/blocks/helpdesk/manage_watchers.php");
                $removeurl->param('tid', $this->get_idstring());
                $removeurl->param('remove', 'true');
                $table->data = array();
                foreach ($watchers as $w) {
                    $row = array();
                    $row[] = helpdesk_user_link($w);
                    if (!$readonly) {
                        $removeurl->param('hd_userid', $w->hd_userid);
                        $row[] = '<a href="' . $removeurl->out() . '">'. get_string('remove') . '</a>';
                    }
                    $table->data[] = $row;
                }
            }
            $table->size = array('70%');
            echo html_writer::table($table, false);
            echo "<br />";
        }


        // START TAGS DISPLAY

        $table->size = array('30%');
        $tagstr = get_string('extradetailtags', 'block_helpdesk');
        $taghelp = $OUTPUT->help_icon('extradetailtags', 'block_helpdesk');
        $thead = $tagstr . $taghelp;

        // If answerer, show link for adding tags.
        if ($isanswerer and !$readonly) {
            $url = new moodle_url("$CFG->wwwroot/blocks/helpdesk/tag.php");
            $url->param('tid', $this->get_idstring());
            $url = $url->out();
            $addtagstr = get_string('addtag', 'block_helpdesk');

            $thead .= "<br /><a href=\"$url\">$addtagstr</a>";
        }
        $table->head = array($thead);

        $table->data = array();
        if (!$tags == null) {
            foreach($tags as $tag) {
                $url = new moodle_url("$CFG->wwwroot/blocks/helpdesk/tag.php");
                $url->param('remove', $tag->id);
                $url->param('tid', $this->get_idstring());
                $url = $url->out();
                $removestr = get_string('remove');

                $row = array();
                if ($isanswerer and !$readonly) {
                    $remove = "<br />
                               <small>
                                   <a href=\"$url\">$removestr</a>
                               </small>";
                } else {
                    $remove = '';
                }
                $table->data[] = array(
                    $tag->name . $remove,
                    file_rewrite_pluginfile_urls($tag->value, 'pluginfile.php',
                        context_system::instance()->id, 'block_helpdesk', 'tickettag', $tag->id)
                );
            }
        } else {
            $table->data = array(array(get_string('notags', 'block_helpdesk')));
        }
        echo html_writer::table($table);

        // END TAGS DISPLAY

        echo '</div>';

        // Updates start here.
        $updatestr = get_string('updates', 'block_helpdesk');
        $updatehelp = $OUTPUT->help_icon('updates', 'block_helpdesk');
        echo "<div class=\"ticketupdates\">";


        $url = new moodle_url("$CFG->wwwroot/blocks/helpdesk/update.php");
        $url->param('id', $this->get_idstring());
        if (!empty($USER->helpdesk_token)) {
            $url->param('token', $USER->helpdesk_token);
        }
        $url = $url->out();
        $translated = get_string('updateticket', 'block_helpdesk');

        $thead = "$updatestr $updatehelp";
        if(!$readonly) {
            $thead .= "<br /><a href=\"$url\">$translated</a>";
        }
        $table->head = array($thead);

        // We're going to find out now if we are displaying these updates.
        $table->data = array();
        $updateprinted = false;
        if (is_array($udata) or is_object($udata)) {
            $update_table = new html_table();
            // If we have system or detailed updates, display them.
            $showdetailed = helpdesk_get_session_var('showdetailedupdates');
            $showsystem = helpdesk_get_session_var('showsystemupdates');
            foreach($udata as $update) {
                $update_table->data = array();
                $update_table->width = '100%';
                $update_table->size = array('80px');
                if ($update->type == HELPDESK_UPDATE_TYPE_DETAILED and !$showdetailed) {
                    continue;
                }
                if ($update->type == HELPDESK_UPDATE_TYPE_SYSTEM and !$showsystem) {
                    continue;
                }

                $updateprinted = true;

                if ($update->type !== false and
                    $update->type !== null) {

                    $row = array();
                    $str = get_string($update->type, 'block_helpdesk');
                    $row[] = $str;
                    if ($hd->is_update_hidden($update)) {
                        $url = new moodle_url("{$CFG->wwwroot}/blocks/helpdesk/plugins/native/action/showupdate.php");
                        $str = get_string('showupdate', 'block_helpdesk');
                    } else {
                        $url = new moodle_url("{$CFG->wwwroot}/blocks/helpdesk/plugins/native/action/hideupdate.php");
                        $str = get_string('hideupdate', 'block_helpdesk');
                    }
                    $url = $url->out();
                    if($isanswerer) {
                    $hideshowbutton = "<form name=\"updatehideshow\"action=\"{$url}\" method=\"get\">" .
                        "<input type=\"submit\" value=\"{$str}\" />" .
                        "<input type=\"hidden\" name=\"id\" value=\"{$update->id}\" /></form>";
                    } else {
                        $hideshowbutton = '';
                    }
                    if ($hd->is_update_hidden($update)) {
                        $row[] = get_string('thisupdateishidden', 'block_helpdesk') . '<br />'
                            . $hideshowbutton;
                    } else {
                        $row[] = $hideshowbutton;
                    }
                    $update_table->head = $row;
                }

                $user = helpdesk_get_hd_user($update->hd_userid);
                if (!$user) {
                    print_error(getstring('unabletopulluser', 'block_helpdesk'));
                }

                // Who submitted the update?
                $row = array();
                $row[] = get_string('user', 'block_helpdesk');
                $row[] = helpdesk_user_link($user);
                $update_table->data[] = $row;

                // Status
                $row = array();
                $row[] = get_string('status', 'block_helpdesk');
                $row[] = get_string($update->status, 'block_helpdesk');
                $update_table->data[] = $row;

                // New ticket status if status changed.
                if ($update->newticketstatus != null) {
                    $row = array();
                    $tstat = $DB->get_record('block_helpdesk_status', array('id' => $update->newticketstatus));
                    $row[] = get_string('newquestionstatus', 'block_helpdesk');
                    $row[] = $this->get_status_string($tstat);
                    $update_table->data[] = $row;
                }

                // "Created On" date.
                $row = array();
                $creation_date = helpdesk_get_date_string($update->timecreated);

                // Time Created date.
                $row[] = get_string('timecreated', 'block_helpdesk');
                $row[] = $creation_date;
                $update_table->data[] = $row;

                // Update Note.
                $row = array();
                $row[] = get_string('note', 'block_helpdesk');
                $row[] = file_rewrite_pluginfile_urls($update->notes, 'pluginfile.php',
                    context_system::instance()->id, 'block_helpdesk', 'ticketnotes', $update->id);
                $update_table->data[] = $row;
                $table->data[] = array(html_writer::table($update_table));
//                echo '<br />';
            }
        }
        if ($updateprinted === false) {
            $row = array();
            $row[] = get_string('noupdatestoview', 'block_helpdesk');
            $update_table->data[] = $row;

        }

        echo html_writer::table($table);

        echo '</div>';
        return true;
    }

    /**
     * Set method to set the idstring of a ticket.
     *
     * @param string    $id idstring to be set to the ticket.
     * @return bool
     */
    function set_idstring($id) {
        $this->id = $id;
        return true;
    }

    /**
     * Set method to set the summary string of a ticket.
     *
     * @param string    $string Summary to be set to the ticket.
     * @return bool
     */
    function set_summary($string) {
        $this->summary = $string;
        return true;
    }

    /**
     * Set method to set the detail string of a ticket.
     *
     * @param string    $string detail string to be set to the ticket.
     * @return bool
     */
    function set_detail($string) {
        $this->detail = $string;
        return true;
    }

    /**
     * Set method to set the timecreated of a ticket. This method doesn't take
     * any parameters, because the only time the time created should be is when
     * this method is called.
     *
     * @return bool
     */
    function set_timecreated() {
        $this->timecreated = time();
        return true;
    }

    /**
     * Set method to set the timemodified of a ticket. This method doesn't take
     * any parameters, because the only time the timemodified should be is when
     * this method is called.
     *
     * @return bool
     */
    function set_timemodified() {
        $this->timemodified = time();
        return true;
    }

    /**
     * Set a new status to the ticket.
     *
     * @param string    $status is a status string to be set.
     * @return bool
     */
    function set_status($status) {
        global $DB;

        if (is_numeric($status)) {
            $status = $DB->get_record('block_helpdesk_status', array('id' => $status));
        }
        if (!is_object($status)) {
            print_error('Status must be an object or id.');
        }
        $this->status = $status;
        return true;
    }

    function set_hd_userid($id) {
        $this->hd_userid = $id;
        return true;
    }

    function set_createdby($id) {
        $this->createdby = $id;
        return true;
    }

    /**
     * Get method that returns an idstring.
     *
     * @return string
     */
    function get_idstring() {
        return $this->id;
    }

    /**
     * Get method that returns the summary of a ticket.
     *
     * @return string
     */
    function get_summary() {
        return $this->summary;
    }

    /**
     * Get method that returns the detail string of a ticket.
     *
     * @return string
     */
    function get_detail() {
        return $this->detail;
    }

    /**
     * Get method that returns the time created in unix epoch form.
     *
     * @return int
     */
    function get_timecreated() {
        return $this->timecreated;
    }

    /**
     * Get method that returns the time modified in unix epoch form.
     *
     * @return int
     */
    function get_timemodified() {
        return $this->timemodified;
    }

    /**
     * Get method that returns the array of tags associated with a ticket.
     *
     * @return array
     */
    function get_tags() {
        return $this->tags;
    }

    /**
     * Get method that returns an array of updates associated with a ticket.
     *
     * @return array
     */
    function get_updates($includehidden=false) {
        if ($includehidden == true) {
            return $this->updates;
        }
        $updates = array();
        if (!empty($this->updates)) {
            foreach($this->updates as $update) {
                if ($update->hidden == 1) {
                    continue;
                }
                $updates[] = $update;
            }
        }
        return $updates;
    }

    /**
     * Gets the id of the user that a particular ticket is for. The output of
     * this method varies from plugin to plugin, this case it returns an int for
     * an id.
     *
     * @return int
     */
    function get_hd_userid() {
        return $this->hd_userid;
    }

    function get_createdby() {
        return $this->createdby;
    }

    /**
     * Returns the value for the status, this should be a simple string.
     *
     * @return string
     */
    function get_status() {
        return $this->status;
    }

    /**
     * Slightly modified get_status_string which will default to the ticket's
     * own status if no argument is passed. Otherwise the argument is used to
     * determine the Moodle status string. Returned value is mixed. String if
     * there is a matching string, or false if not.
     *
     * @param object    $status status to be converted to a native language.
     * @return mixed
     */
    function get_status_string($status=null) {
        // Matt thinks this is evil. Now that we're moving statuses to the
        // database, we need this to do some pre-processing of statuses.
        if ($status != null and !is_object($status)) {
            print_error('non-object ('.gettype($status).') passed to get_status_string()');
        }
        if ($status == null) {
            $status = $this->get_status();
        }

        if ($status->core == true and empty($status->displayname)) {
            return get_string($status->name, 'block_helpdesk');
        }
        return $status->displayname;
    }

    /**
     * This method adds an assignment to a ticket by a user's id. This method
     * assumes that access to be assigned has already been checked.
     *
     * @param int       $userid User id that is being assigned.
     * @return bool
     */
    function add_assignment($userid) {
        global $CFG, $OUTPUT, $DB;

        $assigned = $this->get_assigned();
        if (isset($assigned[$userid])) {
            return true;
        }

        $assign = (object) array(
            'userid'    => $userid,
            'ticketid'  => $this->id,
        );
        if (!$DB->insert_record('block_helpdesk_ticket_assign', $assign)) {
            return false;
        }

        // Now lets add an update for what changed. We want to track things like
        // this from now on.
        $user = helpdesk_get_user($userid);
        $update = (object) array(
            'notes_editor'     => array(),
            'status'    => HELPDESK_NATIVE_UPDATE_ASSIGN,
            'type'      => HELPDESK_UPDATE_TYPE_DETAILED,
        );
        $update->notes_editor['text'] = fullname_nowarnings($user) . ' '
            . get_string('wasassigned', 'block_helpdesk');
        $update->notes_editor['format'] = FORMAT_HTML;
        if(!$this->add_update($update)) {
            echo $OUTPUT->notification(get_string('cantaddupdate', 'block_helpdesk'));
        }

        if (!empty($CFG->block_helpdesk_assigned_auto_watch)) {
            if (!$this->add_watcher($user->hd_userid)) {
                print_error('cannotaddwatcher', 'block_helpdesk');
            }
        } else {
            $this->fetch();
            $this->store();
        }
        return true;
    }

    /**
     * This method removes an assignment from a particular ticket. Users are
     * removed by their user id. This will return true or false depending on the
     * result.
     *
     * @param int       $userid ID of the user to remove the assignment for.
     * @return bool
     */
    function remove_assignment($userid) {
        global $CFG, $DB, $OUTPUT;

        $assigned = $this->get_assigned();
        if (!isset($assigned[$userid])) {
            return true;
        }

        $result = $DB->delete_records('block_helpdesk_ticket_assign', array('userid' => $userid, 'ticketid' => $this->id));
        if ($result) {
            $this->store();
            $user = helpdesk_get_user($userid);
            $update = (object) array(
                'notes_editor'     => array(),
                'status'    => HELPDESK_NATIVE_UPDATE_UNASSIGN,
                'type'      => HELPDESK_UPDATE_TYPE_DETAILED,
            );
            $update->notes_editor['text'] = fullname_nowarnings($user) . ' ' .
                get_string('wasunassigned', 'block_helpdesk');
            $update->notes_editor['format'] = FORMAT_HTML;

            if(!$this->add_update($update)) {
                echo $OUTPUT->notification(get_string('cantaddupdate', 'block_helpdesk'));
            }
        }

        if (!empty($CFG->block_helpdesk_assigned_auto_watch)) {
            if (!$this->remove_watcher($user->hd_userid)) {
                print_error('cannotremovewatcher', 'block_helpdesk');
            }
        }
        return $result;
    }

    /**
     * This gets all the assigned users for a particular ticket. It will return
     * an array of users, similar to a database record array from moodle.
     *
     * @return array
     */
    function get_assigned() {
        global $DB;

        // When a new ticket is stored, there is no id. We want to stop here.
        if (empty($this->id)) {
            return false;
        }
        $records = $DB->get_records('block_helpdesk_ticket_assign', array('ticketid' => $this->id));

        // If there are no records, there are no users assigned.
        if (!$records) {
            return false;
        }

        // At this point we have to process each user. This may sound scary but
        // the number of assigned users is usually low.
        foreach($records as $record) {
            $user = helpdesk_get_user($record->userid);
            $users[$user->userid] = $user;
        }

        return $users;
    }

    /**
     * This method adds a watcher to a ticket. It assumes that checks have already been
     *
     * @param int       $hd_userid hd_user.id of user being added
     * @return bool
     */
    function add_watcher($hd_userid) {
        global $DB, $OUTPUT;

        $watchers = $this->get_watchers();
        if (isset($watchers[$hd_userid])) {
            return true;
        }

        $user = helpdesk_get_hd_user($hd_userid);

        $watcher = (object) array(
            'ticketid'  => $this->id,
            'hd_userid' => $hd_userid,
        );
        if (!$DB->insert_record('block_helpdesk_watcher', $watcher)) { return false; }

        // update
        $update = (object) array(
            'notes_editor' => array('text' => fullname_nowarnings($user) . get_string('startwatching', 'block_helpdesk'),
                'format' => FORMAT_HTML),
            'status'    => HELPDESK_NATIVE_UPDATE_WATCHING,
            'type'      => HELPDESK_UPDATE_TYPE_DETAILED,
        );
        if(!$this->add_update($update)) {
            echo $OUTPUT->notification(get_string('cantaddupdate', 'block_helpdesk'));
        }

        $this->fetch();
        $this->store();
        return true;
    }

    /**
     * This method removes a watcher from a ticket
     *
     * @param int       $hd_userid hd_user.id of user being removed
     */
    function remove_watcher($hd_userid) {
        global $DB, $OUTPUT;

        $watchers = $this->get_watchers();
        if (!isset($watchers[$hd_userid])) {
            return true;
        }

        $result = $DB->delete_records('block_helpdesk_watcher', array('hd_userid' => $hd_userid, 'ticketid' => $this->id));
        if ($result) {
            $this->store();
            $user = helpdesk_get_hd_user($hd_userid);
            $update = (object) array(
                'notes_editor' => array('text' => fullname_nowarnings($user) . get_string('notwatching', 'block_helpdesk'),
                    'format' => FORMAT_HTML),
                'status'    => HELPDESK_NATIVE_UPDATE_NOTWATCHING,
                'type'      => HELPDESK_UPDATE_TYPE_DETAILED,
            );

            if(!$this->add_update($update)) {
                echo $OUTPUT->notification(get_string('cantaddupdate', 'block_helpdesk'));
            }
        }
        return true;
    }

    /**
     * This gets all the watching hd_users for a particular ticket. It will return
     * an array of users, similar to a database record array from moodle.
     *
     * @return array
     */
    function get_watchers() {
        global $DB;

        // When a new ticket is stored, there is no id. We want to stop here.
        if (empty($this->id)) {
            return false;
        }
        $records = $DB->get_records('block_helpdesk_watcher', array('ticketid' => $this->id));

        // If there are no records, there are no users assigned.
        if (!$records) {
            return false;
        }

        // At this point we have to process each user. This may sound scary but
        // the number of assigned users is usually low.
        foreach($records as $record) {
            $user = helpdesk_get_hd_user($record->hd_userid);
            $user = (object) array_merge((array) $user, (array) $record);
            $users[$user->hd_userid] = $user;
        }

        return $users;
    }

    /**
     * The fetch() method gets a ticket and all respective related records that
     * reside inside a ticket object. This is all based off the currently set
     * idstring. If no ID is set or the fetch fails, a false is returned.
     * Otherwise will return true.
     *
     * @return bool
     */
    function fetch($permissionhalt=true) {
        global $USER, $DB;
        if (!$this->id) {
            return false;
        }
        $ticket = $DB->get_record('block_helpdesk_ticket', array('id' => $this->id));
        if (!$ticket) {
            return false;
        }
        if (isset($USER->hd_userid)) {
            $hd_user = $USER;
        } else {
            $hd_user = helpdesk_get_user($USER->id);
        }
        $watchers = $DB->get_records('block_helpdesk_watcher', array('ticketid' => $this->id));
        $iswatcher = false;
        if (!empty($watchers)) {
            foreach ($watchers as $w) {
                if ($w->hd_userid == $hd_user->hd_userid) {
                    $iswatcher = true;
                    break;
                }
            }
        }
        # Check for permission before proceeding.
        if (!helpdesk_is_capable(HELPDESK_CAP_ASK) or !$iswatcher) {
            if (!helpdesk_is_capable(HELPDESK_CAP_ANSWER, $permissionhalt)) {
                return false;
            }
        }

        $this->parse_db_ticket($ticket);
        $updates        = $DB->get_records('block_helpdesk_ticket_update', array('ticketid' => $this->id), 'timecreated DESC');
        $tags           = $DB->get_records('block_helpdesk_ticket_tag', array('ticketid' => $this->id), 'name ASC');
        $this->status   = $DB->get_record('block_helpdesk_status', array('id' => $this->status));
        if(!is_object($this->status)) {
            print_error("Invalid status id on ticket $this->id.");
        }
        $this->parse_db_updates($updates);
        $this->parse_db_tags($tags);

        return true;
    }

    /**
     * store() is a helpdesk_native_ticket method that updates or inserts
     * a record in the database to reflect the data inside this object or
     * updates an already existing record with any changes to the ticket.
     *
     * @return bool
     **/
    function store() {
        global $DB;
        $dataobject                     = new stdClass;
        $dataobject->summary            = $this->summary;
        $dataobject->detail             = $this->detail;
        $dataobject->detailformat       = $this->detailformat;

        if (!is_numeric($this->timecreated)) {
            $this->set_timecreated();
        }

        $dataobject->timecreated        = $this->timecreated;
        $this->set_timemodified();
        $dataobject->timemodified       = $this->timemodified;
        $dataobject->hd_userid             = $this->hd_userid;
        $dataobject->createdby          = $this->createdby;
        if (empty($this->status)) {
            $this->status               = $DB->get_record('block_helpdesk_status', array('ticketdefault' => 1));
        }

        $dataobject->status         = $this->status->id;

        if (is_numeric($this->firstcontact)) {
            if(!$DB->record_exists('user', array('id' => $this->firstcontact))) {
                print_error('Invalid first contact user id.');
            }
            $this->firstcontact = helpdesk_get_user($this->firstcontact);
        }
        $dataobject->firstcontact       = is_object($this->firstcontact) ?
                                          $this->firstcontact->userid : 0;

        $assigned = $this->get_assigned();
        if ($assigned === false) {
            $dataobject->assigned_refs = 0;
        } else {
            $dataobject->assigned_refs  = count($assigned);
        }
        if (!is_numeric($this->hd_userid)) {
            return false;
        }
        if (!empty($this->id)) {
            $dataobject->id = $this->id;
        }

        if (!empty($dataobject->id)) {
            if ($result = $DB->update_record('block_helpdesk_ticket', $dataobject)) {
                $this->fetch();
                return true;
            }
            return false;
        }
        $result = $DB->insert_record('block_helpdesk_ticket', $dataobject);
        if ($result) {
            $this->id = $result;
        } else {
            print_error('error_insertquestion', 'block_helpdesk');
        }

        # add the submitter as a watcher
        $this->add_watcher($this->hd_userid);

        if($this->createdby === $this->hd_userid and get_config(null, 'block_helpdesk_includeagent') == true) {
            $tag = new stdClass;
            $tag->name = get_string('useragent', 'block_helpdesk');
            $tag->value = $_SERVER['HTTP_USER_AGENT'];
            $tag->ticketid = $this->get_idstring();
            $this->add_tag($tag);
            $tag = new stdClass;
            $tag->ticketid = $this->get_idstring();
            $tag->name = get_string('useroperatingsystem', 'block_helpdesk');
            $tag->value = $this->get_os($_SERVER['HTTP_USER_AGENT']);
            $this->add_tag($tag);
        }

        # no need to fetch, as adding the watcher did that
        return true;
    }

    /**
     * Retrieve a ticket based on ID.
     *
     * @param int       $id id of the ticket to be fetched.
     * @return bool
     */
    function get_ticket($id) {
        if (empty($id)) {
            return false;
        }
        $this->set_idstring($id);
        if(!$this->fetch()) {
            return false;
        }
        return true;
    }

    /**
     * This takes the usable field from the $data object you pass in to fill the
     * ticket with some basic information. This is a very generalized method.
     *
     * Deprecate this method! -Jon
     *
     * @param object    $data is an object with ticket fields, such as a db record.
     * @return true
     */
    function parse($data) {
        global $USER;
        if (!is_object($data)) {
            return false;
        }
        $hd_user = helpdesk_get_user($USER->id);
        // An id may not always exists, like if this is a new ticket.
        if (isset($data->id)) {
            $this->id           = $data->id;
        }
        if(!isset($data->detail)) {
            $data->detail = '';
        }
        if(!isset($data->detailformat)) {
            $data->detailformat = FORMAT_HTML;
        }
        $this->detail           = $data->detail;
        $this->detailformat     = $data->detailformat;
        $this->summary          = $data->summary;
        if (empty($data->hd_userid)) {
            $this->hd_userid = $hd_user->hd_userid;
        } else {
            $this->hd_userid    = $data->hd_userid;
        }
        if (empty($data->createdby)) {
            $this->createdby = $hd_user->hd_userid;
        } else {
            $this->createdby = $data->hd_userid;
        }
        if (isset($data->timecreated)) {
            $this->timecreated  = $data->timecreated;
        } else {
            $this->timecreated  = time();
        }
        if (isset($data->timemodified)) {
            $this->timemodified = $data->timemodified;
        } else {
            $this->timemodified = time();
        }
        return true;

    }

    /**
     * Very similar to parse, except this one strips slashes. This takes in
     * database records specifically. Like ones returned from get_record().
     *
     * @param object    $record is a database record from moodle.
     * @return true
     */
    function parse_db_ticket($record) {
        if (!is_object($record)) {
            return false;
        }
        $this->id               = $record->id;
        $this->detail           = stripslashes($record->detail);
        $this->detailformat     = stripslashes($record->detailformat);
        $this->summary          = stripslashes($record->summary);
        $this->hd_userid           = $record->hd_userid;
        $this->createdby        = $record->createdby;
        $this->timecreated      = $record->timecreated;
        $this->timemodified     = $record->timemodified;
        $this->status           = $record->status;
        if (is_numeric($record->firstcontact) and $record->firstcontact != 0) {
            $this->firstcontact = helpdesk_get_user($record->firstcontact);
        } else {
            $this->firstcontact = null;
        }
        return true;
    }

    /**
     * Adds an update to the ticket from the data provided by the moodle form
     * for updates. Returns a bool depending on success.
     *
     * @param object    $update is the update data from a moodle form.
     * @return bool
     */
    function add_update($update) {
        global $USER, $DB, $CFG;
        $hd = helpdesk::get_helpdesk();
        $isanswerer = helpdesk_is_capable(HELPDESK_CAP_ANSWER);
        if (!is_object($update)) {
            return false;
        }
        $dat = new stdclass();
        $dat->ticketid = $this->id;

        // No type is no longer allowed.
        if (!$update->type) {
            return false;
        }

        $status = $this->status;
        $update = $this->process_update($update);

        if (!is_object($this->firstcontact)) {
            if ($isanswerer and empty($USER->helpdesk_external)) {
                $current_hd_user = helpdesk_get_user($USER->id);
                if ($this->get_hd_userid() != $current_hd_user->hd_userid) {
                    $this->firstcontact = $current_hd_user;
                }
            }
        }

        $dat->notes         = '';
        $dat->notesformat   = FORMAT_HTML;
        if (!empty($update->hd_userid)) {
            $dat->hd_userid = $update->hd_userid;
        } else if (!empty($USER->helpdesk_external)) {
            $dat->hd_userid = $USER->hd_userid;
        } else {
            $dat->hd_userid = helpdesk_get_user($USER->id)->hd_userid;
        }
        $dat->status        = $update->status;
        $dat->type          = $update->type;
        $dat->hidden        = isset($update->hidden) ? $update->hidden : false;
        $dat->timecreated   = time();
        $dat->timemodified  = time();
        if(isset($update->newticketstatus)) {
            $dat->newticketstatus   = $update->newticketstatus;
        }

        if ( $updateid = $DB->insert_record('block_helpdesk_ticket_update', $dat) ) {
            $usefirstcontact = get_config(null, 'block_helpdesk_firstcontact');
            $isanswerer = helpdesk_is_capable(HELPDESK_CAP_ANSWER);
            if ($usefirstcontact and $isanswerer and $this->firstcontact == true) {
                $this->firstcontact = helpdesk_get_user($USER->id)->hd_userid;;
            }

            // You're wondering what this is. This actually updates the time
            // modified for the ticket and updates the status if it changed.
            // Not to mension updating firstcontact if it applies.
            $this->store();

            // Lets not fetch, this is quicker.
            $this->updates[] = $dat;

            // We also want to call the email update method in case email
            // notifications are turned on.
            // NOTE: This method will automatically check to see if we can send
            // emails out, don't worry about checking that here.
            if($dat->type == HELPDESK_UPDATE_TYPE_USER) {
                $rval = $hd->email_update($this);
            }

            // Need to update the notes.
            $context = context_system::instance();
            $editoroptions = array('maxfiles'=> 99, 'maxbytes'=> $CFG->maxbytes, 'context'=>$context);
            $dat->id = $updateid;
            $dat->notes_editor = $update->notes_editor;
            $dat = file_postupdate_standard_editor($dat, 'notes', $editoroptions, $context,
                'block_helpdesk', 'ticketnotes', $updateid);
            $DB->update_record('block_helpdesk_ticket_update', $dat);

            return true;
        }

        return false;
    }

    private function process_update($update) {
        global $DB;

        // This allows us to change the status of a ticket at the same time as
        // we add an update.

        // New Method
        // If the status is a number, its a status id to change the ticket to.
        if (is_numeric($update->status)) {
            $this->status = $DB->get_record('block_helpdesk_status', array('id' => $update->status));
            $update->newticketstatus = $this->status->id;
            if (!is_object($this->status)) {
                print_error('Invalid ticket status. Does not exist in status table.');
            }
            $this->store();
            $update->status = HELPDESK_NATIVE_UPDATE_STATUS;
        }
        return $update;
    }

    /**
     * Updates a tag with an id to match the fields on the object.
     *
     * @param object    $tag is a tag record with a constant id.
     * @return bool
     */
    function update_tag($tag) {
        global $DB, $CFG;

        // Update the tag
        $context = context_system::instance();
        $editoroptions = array('maxfiles'=> 99, 'maxbytes'=>$CFG->maxbytes, 'context'=>$context);
        $tag->value = '';
        $tag = file_postupdate_standard_editor($tag, 'value', $editoroptions, $context,
            'block_helpdesk', 'tickettag', $tag->id);

        if ($DB->update_record('block_helpdesk_ticket_tag', $tag)) {
            $this->store();
            $this->fetch();
            return true;
        }
        return false;
    }

    /**
     * Adds a tag to the ticket. The object has the same fields as a tag in the
     * database. There should be no id though.
     *
     * @param object    $tag is a tag to-be record without an id.
     * @return bool
     */
    function add_tag($tag) {
        global $DB, $OUTPUT;

        if (!is_object($tag)) {
            return false;
        }
        if (!isset($tag->name) or
            !isset($tag->value_editor) or
            isset($tag->id)){

            return false;
        }

        $tag->value = '';
        if (!$tag->id = $DB->insert_record('block_helpdesk_ticket_tag', $tag)) {
            return false;
        }

        $this->update_tag($tag);

        // Lets make an update saying we added this tag.
        $dat = new stdClass;
        $dat->ticketid  = $this->id;
        $dat->notes_editor = array('text' => get_string('tagaddedwithnameof', 'block_helpdesk') . $tag->name,
            'format' => FORMAT_HTML);
        $dat->status    = HELPDESK_NATIVE_UPDATE_TAG;
        $dat->type      = HELPDESK_UPDATE_TYPE_DETAILED;

        if(!$this->add_update($dat)) {
            echo $OUTPUT->notification(get_string('cantaddupdate', 'block_helpdesk'));
        }

        // Update modified time and refresh the ticket.
        $this->store();
        $this->fetch();
        return true;
    }

    /**
     * Removes a tag from the database for a certain ticket based on an id.
     *
     * @param int       $id is the id of the tag being removed.
     * @return bool
     */
    function remove_tag($id) {
        global $DB, $OUTPUT;

        if (!is_numeric($id)) {
            return false;
        }

        $tag = $DB->get_record('block_helpdesk_ticket_tag', array('id' => $id));

        $result = $DB->delete_records('block_helpdesk_ticket_tag', array('id' => $id));
        if (!$result) {
            return false;
        }
        // Lets make an update!

        $dat = new stdClass;
        $dat->ticketid      = $this->id;
        $dat->notes_editor  = array('text' => get_string('tagremovewithnameof', 'block_helpdesk') . $tag->name,
            'format' => FORMAT_HTML);
        $dat->status        = HELPDESK_NATIVE_UPDATE_UNTAG;
        $dat->type          = HELPDESK_UPDATE_TYPE_DETAILED;

        if(!$this->add_update($dat)) {
            echo $OUTPUT->notification(get_string('cantaddupdate', 'block_helpdesk'));
        }

        $this->store();
        return true;
    }

    /**
     * Basically copies a $tag array into the ticket. The $tags are usually
     * database records.
     *
     * @param array     $tags are tag records to be included with the ticket.
     * @return null
     */
    function parse_db_tags($tags) {
        if (!is_array($tags)) {
            $this->tags = null;
        }
        $this->tags = $tags;
    }

    /**
     * Basically copies some update records from the database and plops them
     * into our ticket.
     *
     * @param array     $updates is an array of update records from the db.
     * @return true
     */
    function parse_db_updates($updates) {
        if (!is_array($updates)) {
            $this->updates = null;
        }
        $this->updates = $updates;
        return true;
    }

    /**
     * Returns a clean tag in an object. Returns clean tag object or false if
     * failed.
     *
     * @param mixed     $data is an object or array with tag attributes.
     * @return mixed
     */
    function parse_tag($data) {
        $tag = new stdClass();
        if (is_object($data)) {
            if (isset($data->id)) {
                $tag->id = $data->id;
            }
            $tag->name          = $data->name;
            $tag->value_editor  = $data->value_editor;
            $tag->ticketid      = $data->ticketid;
            return $tag;
        } elseif (is_array($data)) {
            if ($data['id']) {
                $tag->id = $data->id;
            }
            $tag->name          = $data['name'];
            $tag->value         = $data['value'];
            $tag->ticketid      = $data['ticketid'];
            return $tag;
        } else {
            return false;
        }
    }

    /**
     * This is calld when an already existing ticket is edited. This allows us 
     * to make an updated associated with this edit.
     *
     * @param string    $notes is a message to leave in the update.
     * @return bool
     */
    function store_edit($noteseditor=null, $newstatus=null) {
        global $OUTPUT;

        if(!$this->store()) {
            return false;
        }
        $update = new stdClass;
        $update->ticketid = $this->id;
        $update->notes_editor = $noteseditor;
        $update->status = HELPDESK_NATIVE_UPDATE_DETAILS;
        $update->type = HELPDESK_UPDATE_TYPE_DETAILED;
        if (isset($newstatus)) {
            $update->newticketstatus = $newstatus;
        }
        if (!$this->add_update($update)) {
            echo $OUTPUT->notification(get_string('unabletoaddeditupdate', 'block_helpdesk'));
        }
        return true;
    }

    private function get_os($agent=false) {
        // the order of this array is important
        $oses = array(
            'Windows 3.11' => 'Win16',
            'Windows 95' => '(Windows 95)|(Win95)|(Windows_95)',
            'Windows ME' => '(Windows 98)|(Win 9x 4.90)|(Windows ME)',
            'Windows 98' => '(Windows 98)|(Win98)',
            'Windows 2000' => '(Windows NT 5.0)|(Windows 2000)',
            'Windows XP' => '(Windows NT 5.1)|(Windows XP)',
            'Windows Server 2003' => '(Windows NT 5.2)',
            'Windows Vista' => '(Windows NT 6.0)',
            'Windows 7' => '(Windows NT 6.1)',
            'Windows NT' => '(Windows NT 4.0)|(WinNT4.0)|(WinNT)|(Windows NT)',
            'OpenBSD' => 'OpenBSD',
            'SunOS' => 'SunOS',
            'Linux' => '(Linux)|(X11)',
            'MacOS' => '(Mac_PowerPC)|(Macintosh)',
            'QNX' => 'QNX',
            'BeOS' => 'BeOS',
            'OS2' => 'OS/2',
            'SearchBot'=>'(nuhk)|(Googlebot)|(Yammybot)|(Openbot)|(Slurp)|(MSNBot)|(Ask Jeeves/Teoma)|(ia_archiver)'
        );
        $agent = strtolower($agent ? $agent : $_SERVER['HTTP_USER_AGENT']);
        foreach($oses as $os=>$pattern) {
            if (preg_match('/'.$pattern.'/i', $agent)) {
                if(preg_match('/WOW64/i', $agent)) {
                    return $os . ' (x64)';
                }
                return $os;
            }
        }
        return 'Unknown';
    }

    /**
     * This gives the user object for the first contact user or false if there 
     * is no firstcontact.
     *
     * @return mixed
     */
    public function get_firstcontact() {
        if(empty($this->firstcontact)) {
            return false;
        }
        return helpdesk_get_user($this->firstcontact->userid);
    }

    /**
     * This only sets the first contact if it isn't already set.
     *
     * @param mixed     $user can be a user object or a user id.
     * @return bool
     */
    public function set_firstcontact($user) {
        if(is_object($user)) {
            $user = $user->id;
        }
        if(empty($user)) {
            print_error('invalidtype', 'block_helpdesk');
        }
        if(empty($this->firstcontact)) {
            $this->firstcontact = $user;
            return true;
        }
        return false;
    }
}
