<?php
function xmldb_block_helpdesk_upgrade($oldversion = 0) {
    global $db, $CFG;
    $result = true;
    require_once("$CFG->dirroot/blocks/helpdesk/lib.php");

    // Any older version at this point.
    if ($result && $oldversion < 2010082700) {
        // Create Ticket Groups Table.
        $table = new XMLDBTable('helpdesk_ticket_group');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL,
                             null, null, null, null);
        $table->addFieldInfo('description', XMLDB_TYPE_TEXT, 'big', null, null,
                             null, null, null, null);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $result && create_table($table);

        // Create Status Table
        $table = new XMLDBTable('helpdesk_status');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL,
                             null, null, null, null);
        $table->addFieldInfo('displayname', XMLDB_TYPE_CHAR, '255', null, null,
                             null, null, null, null);
        $table->addFieldInfo('core', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED,
                             null, null, null, null, null);
        $table->addFieldInfo('whohasball', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED,
                             null, null, null, null, null);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $result && create_table($table);

        // Create Rule Table.
        $table = new XMLDBTable('helpdesk_rule');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL,
                             null, null, null, null);
        $table->addFieldInfo('statusid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('newstatusid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED,
                             null, null, null, null, null);
        $table->addFieldInfo('duration', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('sendemail', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('plainemailbody', XMLDB_TYPE_TEXT, 'big', null, null,
                             null, null, null, null);
        $table->addFieldInfo('htmlemailbody', XMLDB_TYPE_TEXT, 'big', null, null,
                             null, null, null, null);

        // Create Rule Email Table
        $table = new XMLDBTable('helpdesk_rule_email');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('ruleid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('userassoc', XMLDB_TYPE_INTEGER, '5', null,
                             XMLDB_NOTNULL, null, null, null, null);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $result && create_table($table);

        // Now lets add new fields to...
        // Ticket table, groupid
        $table = new XMLDBTable('helpdesk_ticket');
        $field = new XMLDBField('groupid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null,
                              null, null, null, null, 'status');
        $result = $result && add_field($table, $field);

        //Ticket table, first contact.
        $table = new XMLDBTable('helpdesk_ticket');
        $field = new XMLDBField('firstcontact');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null,
                              null, null, null, null, 'groupid');
        $result = $result && add_field($table, $field);
    }

    // Statuses are being move to the database here!
    if ($result && $oldversion < 2010091400) {
        // Add status path table.
        $table = new XMLDBTable('helpdesk_status_path');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('fromstatusid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('tostatusid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED,
                             null, null, null, null, null);
        $table->addFieldInfo('capabilityname', XMLDB_TYPE_CHAR, '255', null,
                             XMLDB_NOTNULL, null, null, null, null);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $result && create_table($table);

        // New fields in status.
        // ticketdefault field.
        $table = new XMLDBTable('helpdesk_status');
        $field = new XMLDBField('ticketdefault');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null,
                              null, null, null, 'whohasball');
        $result = $result && add_field($table, $field);

        // active field.
        $table = new XMLDBTable('helpdesk_status');
        $field = new XMLDBField('active');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL,
                              null, null, null, null, 'ticketdefault');
        $result = $result && add_field($table, $field);

        // We have to convert all the old style statuses over to new statuses.
        // We don't like legacy data in the database. With that said, we need to
        // populate all the statuses, which is normally done when the block is
        // installed. (for all versions starting with this one.)
        $hd = helpdesk::get_helpdesk();
        $hd->install();
        // Lets grab some stuff from the db first.
        $new    = get_field('helpdesk_status', 'id', 'name', 'new');
        $wip    = get_field('helpdesk_status', 'id', 'name', 'workinprogress');
        $closed = get_field('helpdesk_status', 'id', 'name', 'closed');

        // Now our statuses are installed. We're ready to convert legacy to
        // current. This could potentially use a lot of memory.
        $table = new XMLDBTable('helpdesk_ticket');
        $field = new XMLDBField('status');
        $field->setAttributes(XMLDB_TYPE_CHAR, '255', null, null,
                              null, null, null, null, 'assigned_refs');
        $result = $result && rename_field($table, $field, 'oldstatus');

        $table = new XMLDBTable('helpdesk_ticket');
        $field = new XMLDBField('status');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL,
                              null, null, null. null, $new);
        $result = $result && add_field($table, $field);

        // We want to update all tickets without doing them all at once. Some
        // systems may have limited memory.
        $chunksize = 100;       // 100 Records at a time.
        $ticketcount = count_records('helpdesk_ticket');

        // Lets grab all the statuses so we can convert the old ones. This
        // shouldn't be *too* bad.


        // Lets change all tickets to the new status. WOO!
        // We may be able to simplify this.
        $sql = "UPDATE {$CFG->prefix}helpdesk_ticket
                SET status = $new
                WHERE oldstatus = 'new'";
        $result = $result && execute_sql($sql);

        $sql = "UPDATE {$CFG->prefix}helpdesk_ticket
                SET status = $wip
                WHERE oldstatus = 'inprogress'";
        $result = $result && execute_sql($sql);

        $sql = "UPDATE {$CFG->prefix}helpdesk_ticket
                SET status = $closed
                WHERE oldstatus = 'closed'";
        $result = $result && execute_sql($sql);

        // At this point, we're done. Lets get rid of the extra field in the
        // database that has the old statuses.
        $table = new XMLDBTable('helpdesk_ticket');
        $field = new XMLDBField('oldstatus');
        $result = $result && drop_field($table, $field);

        // Lets not forget that we're storing status changes now.
        // So we need that field added to updates.
        $table = new XMLDBTable('helpdesk_ticket_update');
        $field = new XMLDBField('newticketstatus');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null,
                              null, null, null, null, null);
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2011112900) {
        $table = new XMLDBTable('helpdesk_ticket');
        $index = new XMLDBIndex('idx_hd_t_userid');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $result = $result && add_index($table, $index);

        $table = new XMLDBTable('helpdesk_ticket');
        $index = new XMLDBIndex('idx_hd_t_firstcontact');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('firstcontact'));
        $result = $result && add_index($table, $index);

        $table = new XMLDBTable('helpdesk_ticket');
        $index = new XMLDBIndex('idx_hd_t_status');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('status'));
        $result = $result && add_index($table, $index);

        $table = new XMLDBTable('helpdesk_ticket_tag');
        $index = new XMLDBIndex('idx_hd_tt_ticketid');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('ticketid'));
        $result = $result && add_index($table, $index);

        $table = new XMLDBTable('helpdesk_ticket_assignments');
        $index = new XMLDBIndex('idx_hd_ta_userid');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $result = $result && add_index($table, $index);

        $table = new XMLDBTable('helpdesk_ticket_assignments');
        $index = new XMLDBIndex('idx_hd_ta_ticketid');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('ticketid'));
        $result = $result && add_index($table, $index);
    }

    // Rename all tables to the new name...
    if ($result && $oldversion < 2013061702) {
        $table = new XMLDBTable('helpdesk');
        $result = $result && rename_table($table, 'block_helpdesk');

        $table = new XMLDBTable('helpdesk_ticket');
        $result = $result && rename_table($table, 'block_helpdesk_ticket');

        $table = new XMLDBTable('helpdesk_ticket_tag');
        $result = $result && rename_table($table, 'block_helpdesk_ticket_tag');

        $table = new XMLDBTable('helpdesk_ticket_update');
        $result = $result && rename_table($table, 'block_helpdesk_ticket_update');

        $table = new XMLDBTable('helpdesk_ticket_assignments');
        $result = $result && rename_table($table, 'block_helpdesk_ticket_assign');

        $table = new XMLDBTable('helpdesk_ticket_group');
        $result = $result && rename_table($table, 'block_helpdesk_ticket_group');

        $table = new XMLDBTable('helpdesk_status');
        $result = $result && rename_table($table, 'block_helpdesk_status');

        $table = new XMLDBTable('helpdesk_status_path');
        $result = $result && rename_table($table, 'block_helpdesk_status_path');

        $table = new XMLDBTable('helpdesk_rule');
        $result = $result && rename_table($table, 'block_helpdesk_rule');

        $table = new XMLDBTable('helpdesk_rule_email');
        $result = $result && rename_table($table, 'block_helpdesk_rule_email');
    }

    if ($result && $oldversion < 2013072300) {
        /**
         * Create helpdesk_hd_user
         */

        // Define table block_helpdesk_hd_user to be created
        $table = new XMLDBTable('block_helpdesk_hd_user');

        // Adding fields to table block_helpdesk_hd_user
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->addFieldInfo('userid', XMLDB_TYPE_INTEGER, '20');
        $table->addFieldInfo('email', XMLDB_TYPE_CHAR, '100');
        $table->addFieldInfo('phone', XMLDB_TYPE_CHAR, '20');
        $table->addFieldInfo('name', XMLDB_TYPE_CHAR, '100');

        // Adding keys to table block_helpdesk_hd_user
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table block_helpdesk_hd_user
        $table->addIndexInfo('block_helpdesk_hd_user_u_ix', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $table->addIndexInfo('block_helpdesk_hd_user_e_ix', XMLDB_INDEX_NOTUNIQUE, array('email'));
        $table->addIndexInfo('block_helpdesk_hd_user_p_ix', XMLDB_INDEX_NOTUNIQUE, array('phone'));

        $result = $result && create_table($table);


        /**
         * Create helpdesk_hd_user records where needed
         */

        $user2hd_user = array();

        $users = get_records_sql("
            SELECT DISTINCT(userid)
            FROM {$CFG->prefix}block_helpdesk_ticket
            UNION
            SELECT DISTINCT(userid)
            FROM {$CFG->prefix}block_helpdesk_ticket_assign
        ");
        foreach ($users as $record) {
            $hd_userid = insert_record('block_helpdesk_hd_user', $record);
            $user2hd_user[$record->userid] = $hd_userid;
        }


        /**
         * Add helpdesk_ticket.hd_userid
         */

        // Define field hd_userid to be added to block_helpdesk_ticket
        $table = new XMLDBTable('block_helpdesk_ticket');
        $field = new XMLDBField('hd_userid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '20', null, null, null, null, null, null, 'timemodified');

        $result = $result && add_field($table, $field);


        /**
         * Create helpdesk_watcher (ticket to hd_user relation)
         */

        // Define table block_helpdesk_watcher to be created
        $table = new XMLDBTable('block_helpdesk_watcher');

        // Adding fields to table block_helpdesk_watcher
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->addFieldInfo('ticketid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->addFieldInfo('hd_userid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->addFieldInfo('token', XMLDB_TYPE_CHAR, '255', null, null, null, null);

        // Adding keys to table block_helpdesk_watcher
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table block_helpdesk_watcher
        $table->addIndexInfo('block_helpdesk_watcher_t_ix', XMLDB_INDEX_NOTUNIQUE, array('ticketid'));
        $table->addIndexInfo('block_helpdesk_watcher_h_ix', XMLDB_INDEX_NOTUNIQUE, array('hd_userid'));
        $table->addIndexInfo('block_helpdesk_watcher_th_ux', XMLDB_INDEX_UNIQUE, array('ticketid', 'hd_userid'));

        $result = $result && create_table($table);


        /**
         * Create helpdesk_watcher records for ticket submitters, and also
         * populate helpdesk_ticket.hd_userid at the same time
         */

        $watchers = array();
        $tickets = get_records('block_helpdesk_ticket');
        foreach ($tickets as $ticket) {
            # set hd_userid
            $ticket->hd_userid = $user2hd_user[$ticket->userid];
            update_record('block_helpdesk_ticket', $ticket);

            # create watcher rec
            $watcher = (object) array(
                'ticketid' => $ticket->id,
                'hd_userid' => $user2hd_user[$ticket->userid],
            );
            insert_record('block_helpdesk_watcher', $watcher);
            $watchers[$ticket->id . ',' . $user2hd_user[$ticket->userid]] = true;
        }


        /**
         * Create helpdesk_watcher records for ticket assignments
         */

        $ticket_assigns = get_records('block_helpdesk_ticket_assign');
        foreach ($ticket_assigns as $assignment) {
            $key = $assignment->ticketid . ',' . $user2hd_user[$assignment->userid];
            if (!isset($watchers[$key])) {
                $watcher = (object) array(
                    'ticketid' => $assignment->ticketid,
                    'hd_userid' => $user2hd_user[$assignment->userid],
                );
                insert_record('block_helpdesk_watcher', $watcher);
                $watchers[$key] = true;
            }
        }


        /**
         * Add not null requirement for helpdesk_ticket.hd_userid
         */

        // Changing nullability of field hd_userid on table block_helpdesk_ticket to not null
        $table = new XMLDBTable('block_helpdesk_ticket');
        $field = new XMLDBField('hd_userid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null, null, null, 'timemodified');

        // Launch change of nullability for field hd_userid
        $result = $result && change_field_notnull($table, $field);


        /**
         * Create index on helpdesk_ticket.hd_userid
         */

        // Define index block_helpdesk_ticket_h_ix (not unique) to be added to block_helpdesk_ticket
        $table = new XMLDBTable('block_helpdesk_ticket');
        $index = new XMLDBIndex('block_helpdesk_ticket_h_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('hd_userid'));

        // Conditionally launch add index block_helpdesk_ticket_h_ix
        $result = $result && add_index($table, $index);


        /**
         * Add helpdesk_ticket_update.hd_userid
         */

        // Define field hd_userid to be added to block_helpdesk_ticket_update
        $table = new XMLDBTable('block_helpdesk_ticket_update');
        $field = new XMLDBField('hd_userid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '20', null, null, null, null, null, null, 'notes');

        $result = $result && add_field($table, $field);


        /**
         * Update helpdesk_ticket_update.hd_userid for existing tickets
         */

        $ticket_updates = get_records('block_helpdesk_ticket_update');
        foreach ($ticket_updates as $update) {
            $update->hd_userid = $user2hd_user[$update->userid];
            update_record('block_helpdesk_ticket_update', $update);
        }


        /**
         * Add not null requirement for helpdesk_ticket_update.hd_userid
         */

        // Define field id to be added to block_helpdesk_ticket_update
        $table = new XMLDBTable('block_helpdesk_ticket_update');
        $field = new XMLDBField('id');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);

        $result = $result && add_field($table, $field);


        /**
         * Drop the index on helpdesk_ticket.userid
         */

        // Define index idx_hd_t_userid (not unique) to be dropped form block_helpdesk_ticket
        $table = new XMLDBTable('block_helpdesk_ticket');
        $index = new XMLDBIndex('idx_hd_t_userid');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));

        $result = $result && drop_index($table, $index);


        /**
         * Drop helpdesk_ticket.userid
         */

        // Define field userid to be dropped from block_helpdesk_ticket
        $table = new XMLDBTable('block_helpdesk_ticket');
        $field = new XMLDBField('userid');

        $result = $result && drop_field($table, $field);


        /**
         * Drop helpdesk_ticket_update.userid
         */

        // Define field userid to be dropped from block_helpdesk_ticket_update
        $table = new XMLDBTable('block_helpdesk_ticket_update');
        $field = new XMLDBField('userid');

        $result = $result && drop_field($table, $field);


        /**
         * Create an index on helpdesk_ticket_update.ticketid
         */

        // Define index block_helpdesk_ticket_update_t_ix (not unique) to be added to block_helpdesk_ticket_update
        $table = new XMLDBTable('block_helpdesk_ticket_update');
        $index = new XMLDBIndex('block_helpdesk_ticket_update_t_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('ticketid'));

        $result = $result && add_index($table, $index);


        /**
         * Drop helpdesk table
         */
        // Define table block_helpdesk to be dropped
        $table = new XMLDBTable('block_helpdesk');

        $result = $result && drop_table($table);


        /**
         * Drop helpdesk_rule
         */
        // Define table block_helpdesk_rule to be dropped
        $table = new XMLDBTable('block_helpdesk_rule');

        $result = $result && drop_table($table);


        /**
         * Drop helpdesk_rule_email
         */
        // Define table block_helpdesk_rule_email to be dropped
        $table = new XMLDBTable('block_helpdesk_rule_email');

        $result = $result && drop_table($table);


        /**
         * Drop helpdesk_ticket_group
         */
        // Define table block_helpdesk_ticket_group to be dropped
        $table = new XMLDBTable('block_helpdesk_ticket_group');

        $result = $result && drop_table($table);


        /**
         * "Rename" (delete and recreate) various indexes
         */
        // on helpdesk_ticket.firstcontact
        $table = new XMLDBTable('block_helpdesk_ticket');
        $index = new XMLDBIndex('idx_hd_t_firstcontact');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('firstcontact'));
        $result = $result && drop_index($table, $index);

        $table = new XMLDBTable('block_helpdesk_ticket');
        $index = new XMLDBIndex('block_helpdesk_ticket_f_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('firstcontact'));
        $result = $result && add_index($table, $index);


        // on helpdesk_ticket.status
        $table = new XMLDBTable('block_helpdesk_ticket');
        $index = new XMLDBIndex('idx_hd_t_status');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('status'));
        $result = $result && $dbman->drop_index($table, $index);

        $table = new XMLDBTable('block_helpdesk_ticket');
        $index = new XMLDBIndex('block_helpdesk_ticket_st_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('status'));
        $result = $result && add_index($table, $index);


        // on helpdesk_ticket_tag.ticketid
        $table = new XMLDBTable('block_helpdesk_ticket_tag');
        $index = new XMLDBIndex('idx_hd_tt_ticketid');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('ticketid'));
        $result = $result && drop_index($table, $index);

        $table = new XMLDBTable('block_helpdesk_ticket_tag');
        $index = new XMLDBIndex('block_helpdesk_ticket_tag_t_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('ticketid'));
        $result = $result && add_index($table, $index);


        // on block_helpdesk_ticket_assign.userid
        $table = new XMLDBTable('block_helpdesk_ticket_assign');
        $index = new XMLDBIndex('idx_hd_ta_userid');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $result = $result && drop_index($table, $index);

        $table = new XMLDBTable('block_helpdesk_ticket_assign');
        $index = new XMLDBIndex('block_helpdesk_ticket_assign_u_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $result = $result && add_index($table, $index);

        // on block_helpdesk_ticket_assign.ticketid
        $table = new XMLDBTable('block_helpdesk_ticket_assign');
        $index = new XMLDBIndex('idx_hd_ta_ticketid');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('ticketid'));
        $result = $result && drop_index($table, $index);

        $table = new XMLDBTable('block_helpdesk_ticket_assign');
        $index = new XMLDBIndex('block_helpdesk_ticket_assign_t_ix');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('ticketid'));
        $result = $result && add_index($table, $index);
    }

    return $result;
}
