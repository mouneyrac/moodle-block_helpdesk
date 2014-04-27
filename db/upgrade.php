<?php
function xmldb_block_helpdesk_upgrade($oldversion = 0) {
    global $DB, $CFG;
    $dbman = $DB->get_manager();
    $result = true;
    require_once("$CFG->dirroot/blocks/helpdesk/lib.php");

    // Any older version at this point.
    if ($oldversion < 2010082700) {
        // Create Ticket Groups Table.
        $table = new xmldb_table('helpdesk_ticket_group');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL,
                             null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, 'big', null, null,
                             null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $result && $dbman->create_table($table);

        // Create Status Table
        $table = new xmldb_table('helpdesk_status');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL,
                             null, null);
        $table->add_field('displayname', XMLDB_TYPE_CHAR, '255', null, null,
                             null, null);
        $table->add_field('core', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED,
                             null, null, null);
        $table->add_field('whohasball', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED,
                             null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $result && $dbman->create_table($table);

        // Create Rule Table.
        $table = new xmldb_table('helpdesk_rule');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL,
                             null, null);
        $table->add_field('statusid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, null, null);
        $table->add_field('newstatusid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED,
                             null, null, null);
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, null, null);
        $table->add_field('sendemail', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, null, null);
        $table->add_field('plainemailbody', XMLDB_TYPE_TEXT, 'big', null, null,
                             null, null);
        $table->add_field('htmlemailbody', XMLDB_TYPE_TEXT, 'big', null, null,
                             null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $result && $dbman->create_table($table);

        // Create Rule Email Table
        $table = new xmldb_table('helpdesk_rule_email');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ruleid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, null, null);
        $table->add_field('userassoc', XMLDB_TYPE_INTEGER, '5', null,
                             XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $result && $dbman->create_table($table);

        // Now lets add new fields to...
        // Ticket table, groupid
        $table = new xmldb_table('helpdesk_ticket');
        $field = new xmldb_field('groupid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null,
                              null, null, 'status');
        $result = $result && $dbman->add_field($table, $field);

        //Ticket table, first contact.
        $table = new xmldb_table('helpdesk_ticket');
        $field = new xmldb_field('firstcontact');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null,
                              null, null, 'groupid');
        $result = $result && $dbman->add_field($table, $field);
    }

    // Statuses are being move to the database here!
    if ($oldversion < 2010091400) {
        // Add status path table.
        $table = new xmldb_table('helpdesk_status_path');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('fromstatusid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED,
                             XMLDB_NOTNULL, null, null);
        $table->add_field('tostatusid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED,
                             null, null, null);
        $table->add_field('capabilityname', XMLDB_TYPE_CHAR, '255', null,
                             XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $result && $dbman->create_table($table);

        // New fields in status.
        // ticketdefault field.
        $table = new xmldb_table('helpdesk_status');
        $field = new xmldb_field('ticketdefault');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null,
                              null, 'whohasball');
        $result = $result && $dbman->add_field($table, $field);

        // active field.
        $table = new xmldb_table('helpdesk_status');
        $field = new xmldb_field('active');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL,
                              null, null, 'ticketdefault');
        $result = $result && $dbman->add_field($table, $field);

        // We have to convert all the old style statuses over to new statuses.
        // We don't like legacy data in the database. With that said, we need to
        // populate all the statuses, which is normally done when the block is
        // installed. (for all versions starting with this one.)
        $hd = helpdesk::get_helpdesk();
        $hd->install();
        // Lets grab some stuff from the db first.
        $new = $DB->get_field('helpdesk_status', 'id', array('name' => 'new'));
        $wip = $DB->get_field('helpdesk_status', 'id', array('name' => 'workinprogress'));
        $closed = $DB->get_field('helpdesk_status', 'id', array('name' => 'closed'));

        // Now our statuses are installed. We're ready to convert legacy to
        // current. This could potentially use a lot of memory.
        $table = new xmldb_table('helpdesk_ticket');
        $field = new xmldb_field('status');
        $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, null,
                              null, null, 'assigned_refs');
        $result = $result && $dbman->rename_field($table, $field, 'oldstatus');

        $table = new xmldb_table('helpdesk_ticket');
        $field = new xmldb_field('status');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL,
                              null, null, $new);
        $result = $result && $dbman->add_field($table, $field);

        // We want to update all tickets without doing them all at once. Some
        // systems may have limited memory.
        $chunksize = 100;       // 100 Records at a time.
        $ticketcount = $DB->count_records('helpdesk_ticket');

        // Lets grab all the statuses so we can convert the old ones. This
        // shouldn't be *too* bad.


        // Lets change all tickets to the new status. WOO!
        // We may be able to simplify this.
        /**
         * $sql = "UPDATE {$CFG->prefix}helpdesk_ticket
         *         SET status = $new
         *         WHERE oldstatus = 'new'";
         * $result = $result && execute_sql($sql);
         */
        $DB->set_field('helpdesk_ticket', 'status', $new, array('oldstatus' => 'new'));

        /**
         * $sql = "UPDATE {$CFG->prefix}helpdesk_ticket
         *         SET status = $wip
         *         WHERE oldstatus = 'inprogress'";
         * $result = $result && execute_sql($sql);
         */
        $DB->set_field('helpdesk_ticket', 'status', $wip, array('oldstatus' => 'inprogress'));

        /**
         * $sql = "UPDATE {$CFG->prefix}helpdesk_ticket
         *         SET status = $closed
         *         WHERE oldstatus = 'closed'";
         * $result = $result && execute_sql($sql);
         */
        $DB->set_field('helpdesk_ticket', 'status', $closed, array('oldstatus' => 'closed'));

        // At this point, we're done. Lets get rid of the extra field in the
        // database that has the old statuses.
        $table = new xmldb_table('helpdesk_ticket');
        $field = new xmldb_field('oldstatus');
        $result = $result && $dbman->drop_field($table, $field);

        // Lets not forget that we're storing status changes now.
        // So we need that field added to updates.
        $table = new xmldb_table('helpdesk_ticket_update');
        $field = new xmldb_field('newticketstatus');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null,
                              null, null, null);
        $result = $result && $dbman->add_field($table, $field);
    }

    if($oldversion < 2011112900) {
        $table = new xmldb_table('helpdesk_ticket');
        $index = new xmldb_index('idx_hd_t_userid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $result = $result && $dbman->add_index($table, $index);

        $table = new xmldb_table('helpdesk_ticket');
        $index = new xmldb_index('idx_hd_t_firstcontact');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('firstcontact'));
        $result = $result && $dbman->add_index($table, $index);

        $table = new xmldb_table('helpdesk_ticket');
        $index = new xmldb_index('idx_hd_t_status');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('status'));
        $result = $result && $dbman->add_index($table, $index);

        $table = new xmldb_table('helpdesk_ticket_tag');
        $index = new xmldb_index('idx_hd_tt_ticketid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('ticketid'));
        $result = $result && $dbman->add_index($table, $index);

        $table = new xmldb_table('helpdesk_ticket_assignments');
        $index = new xmldb_index('idx_hd_ta_userid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $result = $result && $dbman->add_index($table, $index);

        $table = new xmldb_table('helpdesk_ticket_assignments');
        $index = new xmldb_index('idx_hd_ta_ticketid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('ticketid'));
        $result = $result && $dbman->add_index($table, $index);
    }

    if ($oldversion < 2013022800) {
        $tables = array(
            'helpdesk'                      => 'block_helpdesk',
            'helpdesk_ticket'               => 'block_helpdesk_ticket',
            'helpdesk_ticket_tag'           => 'block_helpdesk_ticket_tag',
            'helpdesk_ticket_update'        => 'block_helpdesk_ticket_update',
            'helpdesk_ticket_assignments'   => 'block_helpdesk_ticket_assign',
            'helpdesk_ticket_group'         => 'block_helpdesk_ticket_group',
            'helpdesk_status'               => 'block_helpdesk_status',
            'helpdesk_status_path'          => 'block_helpdesk_status_path',
            'helpdesk_rule'                 => 'block_helpdesk_rule',
            'helpdesk_rule_email'           => 'block_helpdesk_rule_email'
        );

        foreach ($tables as $old_name => $new_name) {
            // Define table block_helpdesk to be renamed to NEWNAMEGOESHERE
            $table = new xmldb_table($old_name);

            // Launch rename table for block_helpdesk
            $dbman->rename_table($table, $new_name);
        }

        // helpdesk savepoint reached
        upgrade_block_savepoint(true, 2013022800, 'helpdesk');
    }

    if ($oldversion < 2013083000) {
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
                    WHERE u.ticketid = ?
                    AND u.status IN ('".HELPDESK_NATIVE_UPDATE_STATUS."','".HELPDESK_NATIVE_UPDATE_DETAILS."')
                    ORDER BY u.timecreated DESC
                    LIMIT 1
                ";
                if (!$update = $DB->get_record_sql($sql, array($t->id))) {
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
        upgrade_block_savepoint(true, 2013083000, 'helpdesk');
    }

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

    return true;
}
