<?php

function addwatchertable($version, $dbman) {
    global $DB;

        // Define table block_helpdesk_hd_user to be created
        $table = new xmldb_table('block_helpdesk_hd_user');

        // Adding fields to table block_helpdesk_hd_user
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '20');
        $table->add_field('email', XMLDB_TYPE_CHAR, '100');
        $table->add_field('phone', XMLDB_TYPE_CHAR, '20');
        $table->add_field('name', XMLDB_TYPE_CHAR, '100');

        // Adding keys to table block_helpdesk_hd_user
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table block_helpdesk_hd_user
        $table->add_index('block_helpdesk_hd_user_u_ix', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $table->add_index('block_helpdesk_hd_user_e_ix', XMLDB_INDEX_NOTUNIQUE, array('email'));
        $table->add_index('block_helpdesk_hd_user_p_ix', XMLDB_INDEX_NOTUNIQUE, array('phone'));

        $dbman->create_table($table);

        // Define table block_helpdesk_watcher to be created
        $table = new xmldb_table('block_helpdesk_watcher');

        // Adding fields to table block_helpdesk_watcher
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ticketid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null);
        $table->add_field('hd_userid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null);
        $table->add_field('token', XMLDB_TYPE_CHAR, '255', null, null, null);

        // Adding keys to table block_helpdesk_watcher
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table block_helpdesk_watcher
        $table->add_index('block_helpdesk_watcher_t_ix', XMLDB_INDEX_NOTUNIQUE, array('ticketid'));
        $table->add_index('block_helpdesk_watcher_h_ix', XMLDB_INDEX_NOTUNIQUE, array('hd_userid'));
        $table->add_index('block_helpdesk_watcher_th_ux', XMLDB_INDEX_UNIQUE, array('ticketid', 'hd_userid'));

        $dbman->create_table($table);


        /**
         * Create helpdesk_hd_user records where needed
         */

        $user2hd_user = array();

        $users = $DB->get_records_sql("
            SELECT DISTINCT(userid)
            FROM {block_helpdesk_ticket}
            UNION
            SELECT DISTINCT(userid)
            FROM {block_helpdesk_ticket_assign}
        ");
        foreach ($users as $record) {
            $hd_userid = $DB->insert_record('block_helpdesk_hd_user', $record);
            $user2hd_user[$record->userid] = $hd_userid;
        }


        /**
         * Add helpdesk_ticket.hd_userid, .createdby
         */

        // Define field hd_userid to be added to block_helpdesk_ticket
        $table = new xmldb_table('block_helpdesk_ticket');
        $field = new xmldb_field('hd_userid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', null, null, null, null, 'timemodified');

        $dbman->add_field($table, $field);

        /// Define field createdby to be added to block_helpdesk_ticket
        $table = new xmldb_table('block_helpdesk_ticket');
        $field = new xmldb_field('createdby');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, null, 'status');

        /// Launch add field createdby
        $dbman->add_field($table, $field);

        /**
         * Create helpdesk_watcher records for ticket submitters, and also
         * populate helpdesk_ticket.hd_userid & .createdby at the same time
         */

        $watchers = array();
        $tickets = $DB->get_records('block_helpdesk_ticket');
        if (!empty($tickets)) {
            foreach ($tickets as $ticket) {
                # set createdby, hd_userid
                $ticket->createdby = $ticket->hd_userid = $user2hd_user[$ticket->userid];
                $DB->update_record('block_helpdesk_ticket', $ticket);

                # create watcher rec
                $watcher = (object) array(
                    'ticketid' => $ticket->id,
                    'hd_userid' => $user2hd_user[$ticket->userid],
                );

                $DB->insert_record('block_helpdesk_watcher', $watcher);
                $watchers[$ticket->id . ',' . $user2hd_user[$ticket->userid]] = true;
            }
        }


        /**
         * Create helpdesk_watcher records for ticket assignments
         */

        $ticket_assigns = $DB->get_records('block_helpdesk_ticket_assign');
        if (!empty($ticket_assigns)) {
            foreach ($ticket_assigns as $assignment) {
                $key = $assignment->ticketid . ',' . $user2hd_user[$assignment->userid];
                if (!isset($watchers[$key])) {
                    $watcher = (object) array(
                        'ticketid' => $assignment->ticketid,
                        'hd_userid' => $user2hd_user[$assignment->userid],
                    );
                    $DB->insert_record('block_helpdesk_watcher', $watcher);
                    $watchers[$key] = true;
                }
            }
        }


        /**
         * Add not null requirement for helpdesk_ticket.hd_userid, .createdby
         */

        // Changing nullability of field hd_userid on table block_helpdesk_ticket to not null
        $table = new xmldb_table('block_helpdesk_ticket');
        $field = new xmldb_field('hd_userid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null, 'timemodified');

        // Launch change of nullability for field hd_userid
        $dbman->change_field_notnull($table, $field);

        /// Changing nullability of field createdby on table block_helpdesk_ticket to not null
        $table = new xmldb_table('block_helpdesk_ticket');
        $field = new xmldb_field('createdby');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'status');

        /// Launch change of nullability for field createdby
        $dbman->change_field_notnull($table, $field);


        /**
         * Create index on helpdesk_ticket.hd_userid
         */

        // Define index block_helpdesk_ticket_h_ix (not unique) to be added to block_helpdesk_ticket
        $table = new xmldb_table('block_helpdesk_ticket');
        $index = new xmldb_index('block_helpdesk_ticket_h_ix');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('hd_userid'));

        // Conditionally launch add index block_helpdesk_ticket_h_ix
        $dbman->add_index($table, $index);


        /**
         * Add helpdesk_ticket_update.hd_userid
         */

        // Define field hd_userid to be added to block_helpdesk_ticket_update
        $table = new xmldb_table('block_helpdesk_ticket_update');
        $field = new xmldb_field('hd_userid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', null, null, null, null, 'notes');

        $dbman->add_field($table, $field);

        /**
         * Update helpdesk_ticket_update.hd_userid for existing tickets
         */

        $ticket_updates = $DB->get_records('block_helpdesk_ticket_update');
        if (!empty($ticket_updates)) {
            foreach ($ticket_updates as $update) {
                $update->hd_userid = $user2hd_user[$update->userid];
                $DB->update_record('block_helpdesk_ticket_update', $update);
            }
        }

        /**
         * Drop the index on helpdesk_ticket.userid
         */

        // Define index idx_hd_t_userid (not unique) to be dropped form block_helpdesk_ticket
        $table = new xmldb_table('block_helpdesk_ticket');
        $index = new xmldb_index('idx_hd_t_userid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));

        $dbman->drop_index($table, $index);


        /**
         * Drop helpdesk_ticket.userid
         */

        // Define field userid to be dropped from block_helpdesk_ticket
        $table = new xmldb_table('block_helpdesk_ticket');
        $field = new xmldb_field('userid');

        $dbman->drop_field($table, $field);


        /**
         * Drop helpdesk_ticket_update.userid
         */

        // Define field userid to be dropped from block_helpdesk_ticket_update
        $table = new xmldb_table('block_helpdesk_ticket_update');
        $field = new xmldb_field('userid');

        $dbman->drop_field($table, $field);


        /**
         * Create an index on helpdesk_ticket_update.ticketid
         */

        // Define index block_helpdesk_ticket_update_t_ix (not unique) to be added to block_helpdesk_ticket_update
        $table = new xmldb_table('block_helpdesk_ticket_update');
        $index = new xmldb_index('block_helpdesk_ticket_update_t_ix');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('ticketid'));

        $dbman->add_index($table, $index);


        /**
         * Drop helpdesk table
         */
        // Define table block_helpdesk to be dropped
        $table = new xmldb_table('block_helpdesk');

        $dbman->drop_table($table);


        /**
         * Drop helpdesk_rule
         */
        // Define table block_helpdesk_rule to be dropped
        $table = new xmldb_table('block_helpdesk_rule');

        $dbman->drop_table($table);


        /**
         * Drop helpdesk_rule_email
         */
        // Define table block_helpdesk_rule_email to be dropped
        $table = new xmldb_table('block_helpdesk_rule_email');

        $dbman->drop_table($table);


        /**
         * Drop helpdesk_ticket_group
         */
        // Define table block_helpdesk_ticket_group to be dropped
        $table = new xmldb_table('block_helpdesk_ticket_group');

        $dbman->drop_table($table);


        /**
         * "Rename" (delete and recreate) various indexes
         */
        // on helpdesk_ticket.firstcontact
        $table = new xmldb_table('block_helpdesk_ticket');
        $index = new xmldb_index('idx_hd_t_firstcontact');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('firstcontact'));
        $dbman->drop_index($table, $index);

        $table = new xmldb_table('block_helpdesk_ticket');
        $index = new xmldb_index('block_helpdesk_ticket_f_ix');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('firstcontact'));
        $dbman->add_index($table, $index);


        // on helpdesk_ticket.status
        $table = new xmldb_table('block_helpdesk_ticket');
        $index = new xmldb_index('idx_hd_t_status');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('status'));
        $dbman->drop_index($table, $index);

        $table = new xmldb_table('block_helpdesk_ticket');
        $index = new xmldb_index('block_helpdesk_ticket_st_ix');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('status'));
        $dbman->add_index($table, $index);


        // on helpdesk_ticket_tag.ticketid
        $table = new xmldb_table('block_helpdesk_ticket_tag');
        $index = new xmldb_index('idx_hd_tt_ticketid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('ticketid'));
        $dbman->drop_index($table, $index);

        $table = new xmldb_table('block_helpdesk_ticket_tag');
        $index = new xmldb_index('block_helpdesk_ticket_tag_t_ix');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('ticketid'));
        $dbman->add_index($table, $index);


        // on block_helpdesk_ticket_assign.userid
        $table = new xmldb_table('block_helpdesk_ticket_assign');
        $index = new xmldb_index('idx_hd_ta_userid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $dbman->drop_index($table, $index);

        $table = new xmldb_table('block_helpdesk_ticket_assign');
        $index = new xmldb_index('block_helpdesk_ticket_assign_u_ix');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $dbman->add_index($table, $index);

        // on block_helpdesk_ticket_assign.ticketid
        $table = new xmldb_table('block_helpdesk_ticket_assign');
        $index = new xmldb_index('idx_hd_ta_ticketid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('ticketid'));
        $dbman->drop_index($table, $index);

        $table = new xmldb_table('block_helpdesk_ticket_assign');
        $index = new xmldb_index('block_helpdesk_ticket_assign_t_ix');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('ticketid'));
        $dbman->add_index($table, $index);

        /// Define field type to be added to block_helpdesk_hd_user
        $table = new xmldb_table('block_helpdesk_hd_user');
        $field = new xmldb_field('type');
        $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null, 'name');

        /// Launch add field type
        $dbman->add_field($table, $field);

        /// Define field token_last_issued to be added to block_helpdesk_watcher
        $table = new xmldb_table('block_helpdesk_watcher');
        $field = new xmldb_field('token_last_issued');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, null, 'token');

        /// Launch add field token_last_issued
        $dbman->add_field($table, $field);

        $tickets = $DB->get_records_sql("
            SELECT t.id, t.status
            FROM {block_helpdesk_ticket} t
            LEFT JOIN {block_helpdesk_ticket_update} u ON u.ticketid = t.id
            WHERE t.status <> u.newticketstatus
            AND u.id = (
                SELECT u2.id FROM
                {block_helpdesk_ticket_update} u2
                WHERE u2.ticketid = t.id
                AND u2.newticketstatus IS NOT NULL
                ORDER BY timecreated DESC
                LIMIT 1
            )

            UNION

            SELECT t2.id, t2.status
            FROM {block_helpdesk_ticket} t2
            WHERE NOT EXISTS (
                SELECT 1
                FROM {block_helpdesk_ticket_update} u3
                WHERE u3.ticketid = t2.id
                AND u3.newticketstatus = t2.status
            )
            AND t2.status IN (3,4)
        ");
        if ($tickets) {
            require_once("$CFG->dirroot/blocks/helpdesk/plugins/native/lib_native.php");
            foreach ($tickets as $t) {
                $sql = "
                    SELECT *
                    FROM {block_helpdesk_ticket_update} u
                    WHERE u.ticketid = {$t->id}
                    AND u.status IN ('".HELPDESK_NATIVE_UPDATE_STATUS."','".HELPDESK_NATIVE_UPDATE_DETAILS."')
                    ORDER BY u.timecreated DESC
                    LIMIT 1
                ";
                if (!$update = $DB->get_record_sql($sql, false, true)) {
                    echo "No update found to fix newticketstatus bug (ticket.id: $t->id) :(</ br>\n";
                    continue;
                }
                $update->newticketstatus = $t->status;
                echo "updating ticket_update: $update->id with newticketstatus $t->status";
                if (!$DB->update_record('block_helpdesk_ticket_update', $update)) {
                    echo "Couldn't update ticket_update rec (ticket_update.id: $update->id) :(</ br>\n";
                }
            }
        }

        upgrade_block_savepoint(true, $version, 'helpdesk');

        return true;
}


function xmldb_block_helpdesk_upgrade($oldversion = 0) {
    global $DB, $CFG, $USER;
    $result = true;
    $dbman = $DB->get_manager();
    require_once("$CFG->dirroot/blocks/helpdesk/lib.php");

    // Any older version at this point.
    if ($result && $oldversion < 2010082700) {
        // Create Ticket Groups Table.
        $table = new xmldb_table('helpdesk_ticket_group');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $dbman->create_table($table);

        // Create Status Table
        $table = new xmldb_table('helpdesk_status');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('displayname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('core', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('whohasball', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $dbman->create_table($table);

        // Create Rule Table.
        $table = new xmldb_table('helpdesk_rule');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('statusid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('newstatusid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('sendemail', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('plainemailbody', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
        $table->add_field('htmlemailbody', XMLDB_TYPE_TEXT, 'big', null, null, null, null);

        // Create Rule Email Table
        $table = new xmldb_table('helpdesk_rule_email');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ruleid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('userassoc', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $dbman->create_table($table);

        // Now lets add new fields to...
        // Ticket table, groupid
        $table = new xmldb_table('helpdesk_ticket');
        $field = new xmldb_field('groupid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, null, 'status');
        $dbman->add_field($table, $field);

        //Ticket table, first contact.
        $table = new xmldb_table('helpdesk_ticket');
        $field = new xmldb_field('firstcontact');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, null, 'groupid');
        $dbman->add_field($table, $field);
    }

    // Statuses are being move to the database here!
    if ($result && $oldversion < 2010091400) {
        // Add status path table.
        $table = new xmldb_table('helpdesk_status_path');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('fromstatusid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('tostatusid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('capabilityname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $dbman->create_table($table);

        // New fields in status.
        // ticketdefault field.
        $table = new xmldb_table('helpdesk_status');
        $field = new xmldb_field('ticketdefault');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, null, 'whohasball');
        $dbman->add_field($table, $field);

        // active field.
        $table = new xmldb_table('helpdesk_status');
        $field = new xmldb_field('active');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'ticketdefault');
        $dbman->add_field($table, $field);

        // We have to convert all the old style statuses over to new statuses.
        // We don't like legacy data in the database. With that said, we need to
        // populate all the statuses, which is normally done when the block is
        // installed. (for all versions starting with this one.)
        $hd = helpdesk::get_helpdesk();
        $hd->install();
        // Lets grab some stuff from the db first.
        $new    = $DB->get_field('helpdesk_status', 'id', array('name' => 'new'));
        $wip    = $DB->get_field('helpdesk_status', 'id', array('name' => 'workinprogress'));
        $closed = $DB->get_field('helpdesk_status', 'id', array('name' => 'closed'));

        // Now our statuses are installed. We're ready to convert legacy to
        // current. This could potentially use a lot of memory.
        $table = new xmldb_table('helpdesk_ticket');
        $field = new xmldb_field('status');
        $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null, 'assigned_refs');
        $dbman->rename_field($table, $field, 'oldstatus');

        $table = new xmldb_table('helpdesk_ticket');
        $field = new xmldb_field('status');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, $new);
        $dbman->add_field($table, $field);

        // We want to update all tickets without doing them all at once. Some
        // systems may have limited memory.
        $chunksize = 100;       // 100 Records at a time.
        $ticketcount = $DB->count_records('helpdesk_ticket');

        // Lets grab all the statuses so we can convert the old ones. This
        // shouldn't be *too* bad.


        // Lets change all tickets to the new status. WOO!
        // We may be able to simplify this.
        $sql = "UPDATE {helpdesk_ticket}
                SET status = $new
                WHERE oldstatus = 'new'";
        $DB->execute($sql);

        $sql = "UPDATE {helpdesk_ticket}
                SET status = $wip
                WHERE oldstatus = 'inprogress'";
        $DB->execute($sql);

        $sql = "UPDATE {helpdesk_ticket}
                SET status = $closed
                WHERE oldstatus = 'closed'";
        $DB->execute($sql);

        // At this point, we're done. Lets get rid of the extra field in the
        // database that has the old statuses.
        $table = new xmldb_table('helpdesk_ticket');
        $field = new xmldb_field('oldstatus');
        $dbman->drop_field($table, $field);

        // Lets not forget that we're storing status changes now.
        // So we need that field added to updates.
        $table = new xmldb_table('helpdesk_ticket_update');
        $field = new xmldb_field('newticketstatus');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, null, null);
        $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2011112900) {
        $table = new xmldb_table('helpdesk_ticket');
        $index = new xmldb_index('idx_hd_t_userid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $dbman->add_index($table, $index);

        $table = new xmldb_table('helpdesk_ticket');
        $index = new xmldb_index('idx_hd_t_firstcontact');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('firstcontact'));
        $dbman->add_index($table, $index);

        $table = new xmldb_table('helpdesk_ticket');
        $index = new xmldb_index('idx_hd_t_status');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('status'));
        $dbman->add_index($table, $index);

        $table = new xmldb_table('helpdesk_ticket_tag');
        $index = new xmldb_index('idx_hd_tt_ticketid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('ticketid'));
        $dbman->add_index($table, $index);

        $table = new xmldb_table('helpdesk_ticket_assignments');
        $index = new xmldb_index('idx_hd_ta_userid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $dbman->add_index($table, $index);

        $table = new xmldb_table('helpdesk_ticket_assignments');
        $index = new xmldb_index('idx_hd_ta_ticketid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('ticketid'));
        $dbman->add_index($table, $index);
    }

    // Rename all tables to the new name...
    if ($result && $oldversion < 2013061702) {
        $table = new xmldb_table('helpdesk');
        $dbman->rename_table($table, 'block_helpdesk');

        $table = new xmldb_table('helpdesk_ticket');
        $dbman->rename_table($table, 'block_helpdesk_ticket');

        $table = new xmldb_table('helpdesk_ticket_tag');
        $dbman->rename_table($table, 'block_helpdesk_ticket_tag');

        $table = new xmldb_table('helpdesk_ticket_update');
        $dbman->rename_table($table, 'block_helpdesk_ticket_update');

        $table = new xmldb_table('helpdesk_ticket_assignments');
        $dbman->rename_table($table, 'block_helpdesk_ticket_assign');

        $table = new xmldb_table('helpdesk_ticket_group');
        $dbman->rename_table($table, 'block_helpdesk_ticket_group');

        $table = new xmldb_table('helpdesk_status');
        $dbman->rename_table($table, 'block_helpdesk_status');

        $table = new xmldb_table('helpdesk_status_path');
        $dbman->rename_table($table, 'block_helpdesk_status_path');

        $table = new xmldb_table('helpdesk_rule');
        $dbman->rename_table($table, 'block_helpdesk_rule');

        $table = new xmldb_table('helpdesk_rule_email');
        $dbman->rename_table($table, 'block_helpdesk_rule_email');
    }

    // At one point of the time we backported 20 missed commits from master-19 in MOODLE_19_STABLE.
    // These commits created some new fields/tables.
    // So these fields/tables are missing in multiple versions and they need to be added to all versions before 2014042801.
    // However we must ignore these upgrades when the fields/tables are already existing,
    //      i.e. a previous installed helpdesk version already contained the master-19 branch code.
    if ($result) {
        $blockhelpdeskhdusertable = new xmldb_table('block_helpdesk_hd_user');
        $hdusertableupgraded = false;
        if ($dbman->table_exists($blockhelpdeskhdusertable)) {
            $hdusertableupgraded = true;
        }
    }
    if (!$hdusertableupgraded and $oldversion < 2013082906 ) {
        addwatchertable(2013082906 , $dbman);
        $hdusertableupgraded = true;
    }

    // We are adding the format file for file API support.
    if ($oldversion < 2014041902) {

        // Define field detailformat to be added to block_helpdesk_ticket.
        $table = new xmldb_table('block_helpdesk_ticket');
        $field = new xmldb_field('detailformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'detail');

        // Conditionally launch add field detailformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field notesformat to be added to block_helpdesk_ticket_update.
        $table = new xmldb_table('block_helpdesk_ticket_update');
        $field = new xmldb_field('notesformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'notes');

        // Conditionally launch add field notesformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field valueformat to be added to block_helpdesk_ticket_tag.
        $table = new xmldb_table('block_helpdesk_ticket_tag');
        $field = new xmldb_field('valueformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'value');

        // Conditionally launch add field valueformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Helpdesk savepoint reached.
        upgrade_block_savepoint(true, 2014041902, 'helpdesk');
    }

    if (!$hdusertableupgraded and $oldversion < 2014042801) {
        addwatchertable(2014042801, $dbman);
        $hdusertableupgraded = true;
    }

    // We are adding system roles.
    if ($result && $oldversion < 2014042802) {

        if (!$DB->count_records('role', array('shortname' => 'helpdeskmanager'))) {

            // Reminder: do not use core/lib function in upgrade script!
            $contextid = $DB->get_field('context', 'id', array('contextlevel' => CONTEXT_SYSTEM));

            // Hardcode the capability as they must match the value at this upgrade time.
            $HELPDESK_CAP_ASK = 'block/helpdesk:ask';
            $HELPDESK_CAP_ANSWER = 'block/helpdesk:answer';

            // Add Help Desk block manager system role.
            $role = new stdClass();
            $role->name        = 'Help Desk Manager';
            $role->shortname   = 'helpdeskmanager';
            $role->description = 'can answer questions - can do anything on helpdesk.';
            // Find free sortorder number.
            $role->sortorder = $DB->get_field('role', 'MAX(sortorder) + 1', array());
            if (empty($role->sortorder)) {
                $role->sortorder = 1;
            }
            $roleid = $DB->insert_record('role', $role);
            // Set the role as system role.
            $rcl = new stdClass();
            $rcl->roleid = $roleid;
            $rcl->contextlevel = CONTEXT_SYSTEM;
            $DB->insert_record('role_context_levels', $rcl, false, true);
            // Assign correct permission to Help Me Now block manager role.
            $cap = new stdClass();
            $cap->contextid    = $contextid;
            $cap->roleid       = $roleid;
            $cap->capability   = $HELPDESK_CAP_ANSWER;
            $cap->permission   = 1;
            $cap->timemodified = time();
            $cap->modifierid   = empty($USER->id) ? 0 : $USER->id;
            $DB->insert_record('role_capabilities', $cap);
            $cap->capability   = $HELPDESK_CAP_ASK;
            $DB->insert_record('role_capabilities', $cap);
            $cap->capability   = 'block/helpdesk:view';
            $DB->insert_record('role_capabilities', $cap);
            $cap->capability   = 'block/helpdesk:addinstance';
            $DB->insert_record('role_capabilities', $cap);
            $cap->capability   = 'block/helpdesk:myaddinstance';
            $DB->insert_record('role_capabilities', $cap);

            // Add Help Desk block system role.
            $role = new stdClass();
            $role->name        = 'Help Desk user';
            $role->shortname   = 'helpdeskuser';
            $role->description = 'can ask question on help desk.';
            $role->sortorder = $DB->get_field('role', 'MAX(sortorder) + 1', array());
            $roleid = $DB->insert_record('role', $role);
            $rcl = new stdClass();
            $rcl->roleid = $roleid;
            $rcl->contextlevel = CONTEXT_SYSTEM;
            $DB->insert_record('role_context_levels', $rcl, false, true);
            $cap->roleid       = $roleid;
            $cap->capability   = $HELPDESK_CAP_ASK;
            $DB->insert_record('role_capabilities', $cap);
            $cap->capability   = 'block/helpdesk:view';
            $DB->insert_record('role_capabilities', $cap);
            $cap->capability   = 'block/helpdesk:addinstance';
            $DB->insert_record('role_capabilities', $cap);
            $cap->capability   = 'block/helpdesk:myaddinstance';
            $DB->insert_record('role_capabilities', $cap);

            // Helpdesk savepoint reached.
            upgrade_block_savepoint(true, 2014042802, 'helpdesk');

        }
    }

    return true;
}
