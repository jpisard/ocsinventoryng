<?php
/*
 * @version $Id: HEADER 2011-03-12 18:01:26 tsmr $
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2010 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
// ----------------------------------------------------------------------
// Original Author of file: CAILLAUD Xavier
// Purpose of file: plugin ocsinventoryng v 1.0.0 - GLPI 0.83
// ----------------------------------------------------------------------
 */

function plugin_ocsinventoryng_install() {
   global $DB;

   include_once (GLPI_ROOT."/plugins/ocsinventoryng/inc/profile.class.php");

    $migration = new Migration(100);


   if (!TableExists("glpi_plugin_ocsinventoryng_ocsservers")
       && !TableExists("OCS_glpi_ocsservers")) {

      $install = true;
      $DB->runFile(GLPI_ROOT ."/plugins/ocsinventoryng/install/mysql/1.0.0-empty.sql");
      CronTask::Register('PluginOcsinventoryngOcsServer', 'ocsng', MINUTE_TIMESTAMP*5);

   } else if (!TableExists("glpi_plugin_ocsinventoryng_ocsservers")
              && TableExists("OCS_glpi_ocsservers")) {

      $update = true;
      $DB->runFile(GLPI_ROOT ."/plugins/ocsinventoryng/install/mysql/1.0.0-update.sql");

      // recuperation des droits du core
      // creation de la table glpi_plugin_ocsinventoryng_profiles vide
      If (TableExists("OCS_glpi_profiles")
          && (TableExists('OCS_glpi_ocsservers')
              && countElementsInTable('OCS_glpi_ocsservers') > 0)) {

         $query = "INSERT INTO `glpi_plugin_ocsinventoryng_profiles`
                          (`profiles_id`, `ocsng`, `sync_ocsng`, `view_ocsng`, `clean_ocsng`,
                           `rule_ocs`)
                           SELECT `id`, `ocsng`, `sync_ocsng`, `view_ocsng`, `clean_ocsng`,
                                  `rule_ocs`
                           FROM `OCS_glpi_profiles`";
         $DB->queryOrDie($query, "1.0.0 insert profiles for OCS in plugin");
      }

   }

   PluginOcsinventoryngProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);

   // Si massocsimport import est installe, on verifie qu'il soit bien dans la dernière version
   if (TableExists("glpi_plugin_mass_ocs_import")) { //1.1 ou 1.2
      if (!FieldExists('glpi_plugin_mass_ocs_import_config','warn_if_not_imported')) { //1.1
         plugin_ocsinventoryng_upgrademassocsimport11to12();
      }
   }
   if (TableExists("glpi_plugin_mass_ocs_import")) { //1.2 because if before
      plugin_ocsinventoryng_upgrademassocsimport121to13();
   }
   if (TableExists("glpi_plugin_massocsimport")) { //1.3 ou 1.4
      if (FieldExists('glpi_plugin_massocsimport','ID')) { //1.3
         plugin_ocsinventoryng_upgrademassocsimport13to14();
      }
   }
   if (TableExists('glpi_plugin_massocsimport_threads')
         && !FieldExists('glpi_plugin_massocsimport_threads','not_unique_machines_number')) {
         plugin_ocsinventoryng_upgrademassocsimport14to15();
   }

   //Tables from massocsimport
   if (!TableExists('glpi_plugin_ocsinventoryng_threads')
       && !TableExists('glpi_plugin_massocsimport_threads')) { //not installed

      $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_ocsinventoryng_threads` (
                  `id` int(11) NOT NULL auto_increment,
                  `threadid` int(11) NOT NULL default '0',
                  `start_time` datetime default NULL,
                  `end_time` datetime default NULL,
                  `status` int(11) NOT NULL default '0',
                  `error_msg` text NOT NULL,
                  `imported_machines_number` int(11) NOT NULL default '0',
                  `synchronized_machines_number` int(11) NOT NULL default '0',
                  `failed_rules_machines_number` int(11) NOT NULL default '0',
                  `linked_machines_number` int(11) NOT NULL default '0',
                  `notupdated_machines_number` int(11) NOT NULL default '0',
                  `not_unique_machines_number` int(11) NOT NULL default '0',
                  `link_refused_machines_number` int(11) NOT NULL default '0',
                  `total_number_machines` int(11) NOT NULL default '0',
                  `plugin_ocsinventoryng_ocsservers_id` int(11) NOT NULL default '1',
                  `processid` int(11) NOT NULL default '0',
                  `entities_id` int(11) NOT NULL DEFAULT 0,
                  `rules_id` int(11) NOT NULL DEFAULT 0,
                  PRIMARY KEY  (`id`),
                  KEY `end_time` (`end_time`),
                  KEY `process_thread` (`processid`,`threadid`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";

      $DB->queryOrDie($query, $DB->error());


      $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_ocsinventoryng_configs` (
                  `id` int(11) NOT NULL auto_increment,
                  `thread_log_frequency` int(11) NOT NULL default '10',
                  `is_displayempty` int(1) NOT NULL default '1',
                  `import_limit` int(11) NOT NULL default '0',
                  `plugin_ocsinventoryng_ocsservers_id` int(11) NOT NULL default '-1',
                  `delay_refresh` int(11) NOT NULL default '0',
                  `allow_ocs_update` tinyint(1) NOT NULL default '0',
                  `comment` text,
                  PRIMARY KEY (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";

      $DB->queryOrDie($query, $DB->error());

      $query = "INSERT INTO `glpi_plugin_ocsinventoryng_configs`
                       (`id`,`thread_log_frequency`,`is_displayempty`,`import_limit`,
                        `plugin_ocsinventoryng_ocsservers_id`)
                VALUES (1, 2, 1, 0,-1);";
      $DB->queryOrDie($query, $DB->error());


      $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_ocsinventoryng_details` (
                  `id` int(11) NOT NULL auto_increment,
                  `entities_id` int(11) NOT NULL default '0',
                  `plugin_ocsinventoryng_threads_id` int(11) NOT NULL default '0',
                  `rules_id` TEXT,
                  `threadid` int(11) NOT NULL default '0',
                  `ocsid` int(11) NOT NULL default '0',
                  `computers_id` int(11) NOT NULL default '0',
                  `action` int(11) NOT NULL default '0',
                  `process_time` datetime DEFAULT NULL,
                  `plugin_ocsinventoryng_ocsservers_id` int(11) NOT NULL default '1',
                  PRIMARY KEY (`id`),
                  KEY `end_time` (`process_time`),
                  KEY `process_thread` (`plugin_ocsinventoryng_threads_id`,`threadid`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

      $DB->queryOrDie($query, $DB->error());

      $query = "INSERT INTO `glpi_displaypreferences` (`itemtype`, `num`, `rank`, `users_id`)
                VALUES ('PluginOcsinventoryngNotimported', 2, 1, 0),
                       ('PluginOcsinventoryngNotimported', 3, 2, 0),
                       ('PluginOcsinventoryngNotimported', 4, 3, 0),
                       ('PluginOcsinventoryngNotimported', 5, 4, 0),
                       ('PluginOcsinventoryngNotimported', 6, 5, 0),
                       ('PluginOcsinventoryngNotimported', 7, 6, 0),
                       ('PluginOcsinventoryngNotimported', 8, 7, 0),
                       ('PluginOcsinventoryngNotimported', 9, 8, 0),
                       ('PluginOcsinventoryngNotimported', 10, 9, 0),
                       ('PluginOcsinventoryngDetail', 5, 1, 0),
                       ('PluginOcsinventoryngDetail', 2, 2, 0),
                       ('PluginOcsinventoryngDetail', 3, 3, 0),
                       ('PluginOcsinventoryngDetail', 4, 4, 0),
                       ('PluginOcsinventoryngDetail', 6, 5, 0),
                       ('PluginOcsinventoryngDetail', 80, 6, 0)";
      $DB->queryOrDie($query, $DB->error());


      $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_ocsinventoryng_notimported` (
                  `id` INT( 11 ) NOT NULL  auto_increment,
                  `entities_id` int(11) NOT NULL default '0',
                  `rules_id` TEXT,
                  `comment` text NULL,
                  `ocsid` INT( 11 ) NOT NULL DEFAULT '0',
                  `plugin_ocsinventoryng_ocsservers_id` INT( 11 ) NOT NULL ,
                  `ocs_deviceid` VARCHAR( 255 ) NOT NULL ,
                  `useragent` VARCHAR( 255 ) NOT NULL ,
                  `tag` VARCHAR( 255 ) NOT NULL ,
                  `serial` VARCHAR( 255 ) NOT NULL ,
                  `name` VARCHAR( 255 ) NOT NULL ,
                  `ipaddr` VARCHAR( 255 ) NOT NULL ,
                  `domain` VARCHAR( 255 ) NOT NULL ,
                  `last_inventory` DATETIME ,
                  `reason` INT( 11 ) NOT NULL ,
                  PRIMARY KEY ( `id` ),
                  UNIQUE KEY `ocs_id` (`plugin_ocsinventoryng_ocsservers_id`,`ocsid`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";

      $DB->queryOrDie($query, $DB->error());


      $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_ocsinventoryng_servers` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `plugin_ocsinventoryng_ocsservers_id` int(11) NOT NULL DEFAULT '0',
                  `max_ocsid` int(11) DEFAULT NULL,
                  `max_glpidate` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `plugin_ocsinventoryng_ocsservers_id` (`plugin_ocsinventoryng_ocsservers_id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";

      $DB->queryOrDie($query, $DB->error());

      $query = "SELECT `id`
                FROM `glpi_notificationtemplates`
                WHERE `itemtype` = 'PluginOcsinventoryngNotimported'";
      $result = $DB->query($query);

      if (!$DB->numrows($result)) {
         //Add template
         $query = "INSERT INTO `glpi_notificationtemplates`
                   VALUES (NULL, 'Computers not imported', 'PluginOcsinventoryngNotimported',
                           NOW(), '', NULL);";
         $DB->queryOrDie($query, $DB->error());
         $templates_id = $DB->insert_id();
         $query = "INSERT INTO `glpi_notificationtemplatetranslations`
                   VALUES (NULL, $templates_id, '',
                           '##lang.notimported.action## : ##notimported.entity##',
                   '\r\n\n##lang.notimported.action## :&#160;##notimported.entity##\n\n" .
                  "##FOREACHnotimported##&#160;\n##lang.notimported.reason## : ##notimported.reason##\n" .
                  "##lang.notimported.name## : ##notimported.name##\n" .
                  "##lang.notimported.deviceid## : ##notimported.deviceid##\n" .
                  "##lang.notimported.tag## : ##notimported.tag##\n##lang.notimported.serial## : ##notimported.serial## \r\n\n" .
                  " ##notimported.url## \n##ENDFOREACHnotimported## \r\n', '&lt;p&gt;##lang.notimported.action## :&#160;##notimported.entity##&lt;br /&gt;&lt;br /&gt;" .
                  "##FOREACHnotimported##&#160;&lt;br /&gt;##lang.notimported.reason## : ##notimported.reason##&lt;br /&gt;" .
                  "##lang.notimported.name## : ##notimported.name##&lt;br /&gt;" .
                  "##lang.notimported.deviceid## : ##notimported.deviceid##&lt;br /&gt;" .
                  "##lang.notimported.tag## : ##notimported.tag##&lt;br /&gt;" .
                  "##lang.notimported.serial## : ##notimported.serial##&lt;/p&gt;\r\n&lt;p&gt;&lt;a href=\"##notimported.url##\"&gt;" .
                  "##notimported.url##&lt;/a&gt;&lt;br /&gt;##ENDFOREACHnotimported##&lt;/p&gt;');";
         $DB->queryOrDie($query, $DB->error());

         $query = "INSERT INTO `glpi_notifications`
                   VALUES (NULL, 'Computers not imported', 0, 'PluginOcsinventoryngNotimported',
                           'not_imported', 'mail',".$templates_id.", '', 1, 1, NOW());";
         $DB->queryOrDie($query, $DB->error());

      }

      CronTask::Register('PluginOcsinventoryngThread', 'CleanOldThreads', HOUR_TIMESTAMP,
                         array('param' => 24));

   } else if (!TableExists('glpi_plugin_ocsinventoryng_threads')
              && TableExists('glpi_plugin_massocsimport_threads')) {

      if (TableExists('glpi_plugin_massocsimport_threads')
         && !FieldExists('glpi_plugin_massocsimport_threads','not_unique_machines_number')) {
            plugin_ocsinventoryng_massocsimport_upgrade14to15();
      }
      $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_ocsinventoryng_threads` (
                  `id` int(11) NOT NULL auto_increment,
                  `threadid` int(11) NOT NULL default '0',
                  `start_time` datetime default NULL,
                  `end_time` datetime default NULL,
                  `status` int(11) NOT NULL default '0',
                  `error_msg` text NOT NULL,
                  `imported_machines_number` int(11) NOT NULL default '0',
                  `synchronized_machines_number` int(11) NOT NULL default '0',
                  `failed_rules_machines_number` int(11) NOT NULL default '0',
                  `linked_machines_number` int(11) NOT NULL default '0',
                  `notupdated_machines_number` int(11) NOT NULL default '0',
                  `not_unique_machines_number` int(11) NOT NULL default '0',
                  `link_refused_machines_number` int(11) NOT NULL default '0',
                  `total_number_machines` int(11) NOT NULL default '0',
                  `ocsservers_id` int(11) NOT NULL default '1',
                  `processid` int(11) NOT NULL default '0',
                  `entities_id` int(11) NOT NULL DEFAULT 0,
                  `rules_id` int(11) NOT NULL DEFAULT 0,
                  PRIMARY KEY  (`id`),
                  KEY `end_time` (`end_time`),
                  KEY `process_thread` (`processid`,`threadid`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";

      $DB->queryOrDie($query, $DB->error());

      //error of massocsimport 1.5.0 installaton
      $migration->addField("glpi_plugin_massocsimport_threads", "entities_id", 'integer');
      $migration->addField("glpi_plugin_massocsimport_threads", "rules_id", 'integer');

      foreach (getAllDatasFromTable('glpi_plugin_massocsimport_threads') as $thread) {

         $query = "INSERT INTO `glpi_plugin_ocsinventoryng_threads`
                   VALUES ('".$thread['id']."',
                           '".$thread['threadid']."',
                           '".$thread['start_time']."',
                           '".$thread['end_time']."',
                           '".$thread['status']."',
                           '".$thread['error_msg']."',
                           '".$thread['imported_machines_number']."',
                           '".$thread['synchronized_machines_number']."',
                           '".$thread['failed_rules_machines_number']."',
                           '".$thread['linked_machines_number']."',
                           '".$thread['notupdated_machines_number']."',
                           '".$thread['not_unique_machines_number']."',
                           '".$thread['link_refused_machines_number']."',
                           '".$thread['total_number_machines']."',
                           '".$thread['ocsservers_id']."',
                           '".$thread['processid']."',
                           '".$thread['entities_id']."',
                           '".$thread['rules_id']."');";
         $DB->queryOrDie($query, $DB->error());
      }

      $migration->renameTable("glpi_plugin_massocsimport_threads",
                              "backup_glpi_plugin_massocsimport_threads");

      $migration->changeField("glpi_plugin_ocsinventoryng_threads", "ocsservers_id",
                              "plugin_ocsinventoryng_ocsservers_id", 'integer');

      $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_ocsinventoryng_configs` (
                  `id` int(11) NOT NULL auto_increment,
                  `thread_log_frequency` int(11) NOT NULL default '10',
                  `is_displayempty` int(1) NOT NULL default '1',
                  `import_limit` int(11) NOT NULL default '0',
                  `ocsservers_id` int(11) NOT NULL default '-1',
                  `delay_refresh` int(11) NOT NULL default '0',
                  `allow_ocs_update` tinyint(1) NOT NULL default '0',
                  `comment` text,
                  PRIMARY KEY (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";

      $DB->query($query) or die($DB->error());

      foreach (getAllDatasFromTable('glpi_plugin_massocsimport_configs') as $thread) {

         $query = "INSERT INTO `glpi_plugin_ocsinventoryng_configs`
                   VALUES('".$thread['id']."',
                          '".$thread['thread_log_frequency']."',
                          '".$thread['is_displayempty']."',
                          '".$thread['import_limit']."',
                          '".$thread['ocsservers_id']."',
                          '".$thread['delay_refresh']."',
                          '".$thread['allow_ocs_update']."',
                          '".$thread['comment']."');";
         $DB->queryOrDie($query, $DB->error());
      }

      $migration->renameTable("glpi_plugin_massocsimport_configs",
                              "backup_glpi_plugin_massocsimport_configs");

      $migration->changeField("glpi_plugin_ocsinventoryng_configs", "ocsservers_id",
                              "plugin_ocsinventoryng_ocsservers_id", 'integer');


      $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_ocsinventoryng_details` (
                  `id` int(11) NOT NULL auto_increment,
                  `entities_id` int(11) NOT NULL default '0',
                  `plugin_massocsimport_threads_id` int(11) NOT NULL default '0',
                  `rules_id` TEXT,
                  `threadid` int(11) NOT NULL default '0',
                  `ocsid` int(11) NOT NULL default '0',
                  `computers_id` int(11) NOT NULL default '0',
                  `action` int(11) NOT NULL default '0',
                  `process_time` datetime DEFAULT NULL,
                  `ocsservers_id` int(11) NOT NULL default '1',
                  PRIMARY KEY (`id`),
                  KEY `end_time` (`process_time`),
                  KEY `process_thread` (`ocsservers_id`,`threadid`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

      $DB->queryOrDie($query, $DB->error());

      foreach (getAllDatasFromTable('glpi_plugin_massocsimport_details') as $thread) {

         $query = "INSERT INTO `glpi_plugin_ocsinventoryng_details`
                   VALUES ('".$thread['id']."',
                           '".$thread['entities_id']."',
                           '".$thread['plugin_massocsimport_threads_id']."',
                           '".$thread['rules_id']."',
                           '".$thread['threadid']."',
                           '".$thread['ocsid']."',
                           '".$thread['computers_id']."',
                           '".$thread['action']."',
                           '".$thread['process_time']."',
                           '".$thread['ocsservers_id']."');";
         $DB->query($query) or die($DB->error());
      }

      $migration->renameTable("glpi_plugin_massocsimport_details",
                              "backup_glpi_plugin_massocsimport_details");

      $migration->changeField("glpi_plugin_ocsinventoryng_details",
                              "plugin_massocsimport_threads_id", "plugin_ocsinventoryng_threads_id",
                              'integer');

      $migration->changeField("glpi_plugin_ocsinventoryng_details", "ocsservers_id",
                              "plugin_ocsinventoryng_ocsservers_id", 'integer');


      $query = "UPDATE `glpi_displaypreferences`
                SET `itemtype` = 'PluginOcsinventoryngNotimported'
                WHERE `itemtype` = 'PluginMassocsimportNotimported'";

      $DB->queryOrDie($query, $DB->error());

      $query = "UPDATE `glpi_displaypreferences`
                SET `itemtype` = 'PluginOcsinventoryngDetail'
                WHERE `itemtype` = 'PluginMassocsimportDetail';";

      $DB->queryOrDie($query, $DB->error());


      $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_ocsinventoryng_notimported` (
                  `id` INT( 11 ) NOT NULL  auto_increment,
                  `entities_id` int(11) NOT NULL default '0',
                  `rules_id` TEXT,
                  `comment` text NULL,
                  `ocsid` INT( 11 ) NOT NULL DEFAULT '0',
                  `ocsservers_id` INT( 11 ) NOT NULL ,
                  `ocs_deviceid` VARCHAR( 255 ) NOT NULL ,
                  `useragent` VARCHAR( 255 ) NOT NULL ,
                  `tag` VARCHAR( 255 ) NOT NULL ,
                  `serial` VARCHAR( 255 ) NOT NULL ,
                  `name` VARCHAR( 255 ) NOT NULL ,
                  `ipaddr` VARCHAR( 255 ) NOT NULL ,
                  `domain` VARCHAR( 255 ) NOT NULL ,
                  `last_inventory` DATETIME ,
                  `reason` INT( 11 ) NOT NULL ,
                  PRIMARY KEY ( `id` ),
                  UNIQUE KEY `ocs_id` (`ocsservers_id`,`ocsid`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";

      $DB->queryOrDie($query, $DB->error());

      if (TableExists("glpi_plugin_massocsimport_notimported")) {
         foreach (getAllDatasFromTable('glpi_plugin_massocsimport_notimported') as $thread) {

            $query = "INSERT INTO `glpi_plugin_ocsinventoryng_notimported`
                      VALUES ('".$thread['id']."', '".$thread['entities_id']."',
                              '".$thread['rules_id']."', '".$thread['comment']."',
                              '".$thread['ocsid']."', '".$thread['ocsservers_id']."',
                              '".$thread['ocs_deviceid']."', '".$thread['useragent']."',
                              '".$thread['tag']."', '".$thread['serial']."', '".$thread['name']."',
                              '".$thread['ipaddr']."', '".$thread['domain']."',
                              '".$thread['last_inventory']."', '".$thread['reason']."')";
            $DB->queryOrDie($query, $DB->error());
         }

         $migration->renameTable("glpi_plugin_massocsimport_notimported",
                                 "backup_glpi_plugin_massocsimport_notimported");
      }

      $migration->changeField("glpi_plugin_ocsinventoryng_notimported", "ocsservers_id",
                              "`plugin_ocsinventoryng_ocsservers_id", 'integer');

      $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_ocsinventoryng_servers` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `ocsservers_id` int(11) NOT NULL DEFAULT '0',
                  `max_ocsid` int(11) DEFAULT NULL,
                  `max_glpidate` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `ocsservers_id` (`ocsservers_id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";

      $DB->query($query) or die($DB->error());

      foreach (getAllDatasFromTable('glpi_plugin_massocsimport_servers') as $thread) {

         $query = "INSERT INTO `glpi_plugin_ocsinventoryng_servers`
                          (`id` ,`ocsservers_id` ,`max_ocsid` ,`max_glpidate`)
                   VALUES ('".$thread['id']."',
                           '".$thread['ocsservers_id']."',
                           '".$thread['max_ocsid']."',
                           '".$thread['max_glpidate']."');";
         $DB->queryOrDie($query, $DB->error());
      }

      $migration->renameTable("glpi_plugin_massocsimport_servers",
                              "backup_glpi_plugin_massocsimport_servers");

      $migration->changeField("glpi_plugin_ocsinventoryng_servers", "ocsservers_id",
                              "plugin_ocsinventoryng_ocsservers_id", 'integer');


      $query = "UPDATE `glpi_notificationtemplates`
                SET `itemtype` = 'PluginOcsinventoryngNotimported'
                WHERE `itemtype` = 'PluginMassocsimportNotimported'";

      $DB->queryOrDie($query, $DB->error());

      $query = "UPDATE `glpi_notifications`
                SET `itemtype` = 'PluginOcsinventoryngNotimported'
                WHERE `itemtype` = 'PluginMassocsimportNotimported'";

      $DB->queryOrDie($query, $DB->error());

      $query = "UPDATE `glpi_crontasks`
                SET `itemtype` = 'PluginOcsinventoryngThread'
                WHERE `itemtype` = 'PluginMassocsimportThread';";
      $DB->queryOrDie($query, $DB->error());
   }

   $migration->executeMigration();

   return true;
}



function plugin_ocsinventoryng_upgrademassocsimport11to12() {
   global $DB;

   $migration= new Migration(12);

   if (!TableExists("glpi_plugin_mass_ocs_import_config")) {
      $query = "CREATE TABLE `glpi_plugin_mass_ocs_import_config` (
                  `ID` int(11) NOT NULL,
                  `enable_logging` int(1) NOT NULL default '1',
                  `thread_log_frequency` int(4) NOT NULL default '10',
                  `display_empty` int(1) NOT NULL default '1',
                  `delete_frequency` int(4) NOT NULL default '0',
                  `import_limit` int(11) NOT NULL default '0',
                  `default_ocs_server` int(11) NOT NULL default '-1',
                  `delay_refresh` varchar(4) NOT NULL default '0',
                  `delete_empty_frequency` int(4) NOT NULL default '0',
                  PRIMARY KEY  (`ID`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";

      $DB->queryOrDie($query, "1.1 to 1.2 ".$DB->error());

      $query = "INSERT INTO `glpi_plugin_mass_ocs_import_config`
                     (`ID`, `enable_logging`, `thread_log_frequency`, `display_empty`,
                      `delete_frequency`, `delete_empty_frequency`, `import_limit`,
                      `default_ocs_server` )
                VALUES (1, 1, 5, 1, 2, 2, 0,-1)";

      $DB->queryOrDie($query, "1.1 to 1.2 ".$DB->error());
   }

   $migration->addField("glpi_plugin_mass_ocs_import_config", "warn_if_not_imported", 'integer');
   $migration->addField("glpi_plugin_mass_ocs_import_config", "not_imported_threshold", 'integer');

   $migration->executeMigration();
}


function plugin_ocsinventoryng_upgrademassocsimport121to13() {
   global $DB;

   $migration = new Migration(13);

   if (TableExists("glpi_plugin_mass_ocs_import_config")) {
      $tables = array("glpi_plugin_massocsimport_servers" => "glpi_plugin_mass_ocs_import_servers",
                      "glpi_plugin_massocsimport"         => "glpi_plugin_mass_ocs_import",
                      "glpi_plugin_massocsimport_config"  => "glpi_plugin_mass_ocs_import_config",
                      "glpi_plugin_massocsimport_not_imported"
                                                          => "glpi_plugin_mass_ocs_import_not_imported");

      foreach ($tables as $new => $old) {
         $migration->renameTable($old, $new);
      }

      $migration->changeField("glpi_plugin_massocsimport", "process_id", "process_id",
                              "BIGINT(20) NOT NULL DEFAULT '0'");

      $migration->addField("glpi_plugin_massocsimport_config", "comments", 'text');

      $migration->addField("glpi_plugin_massocsimport", "noupdate_machines_number", 'integer');

      if (!TableExists("glpi_plugin_massocsimport_details")) {
         $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_massocsimport_details` (
                     `ID` int(11) NOT NULL auto_increment,
                     `process_id` bigint(10) NOT NULL default '0',
                     `thread_id` int(4) NOT NULL default '0',
                     `ocs_id` int(11) NOT NULL default '0',
                     `glpi_id` int(11) NOT NULL default '0',
                     `action` int(11) NOT NULL default '0',
                     `process_time` datetime DEFAULT NULL,
                     `ocs_server_id` int(4) NOT NULL default '1',
                     PRIMARY KEY  (`ID`),
                     KEY `end_time` (`process_time`)
                   ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
         $DB->queryOrDie($query, "1.2.1 to 1.3 ".$DB->error());
      }

      //Add fields to the default view
      $query = "INSERT INTO `glpi_displayprefs` (`itemtype`, `num`, `rank`, `users_id`)
                VALUES (" . PLUGIN_MASSOCSIMPORT_NOTIMPORTED . ", 2, 1, 0),
                       (" . PLUGIN_MASSOCSIMPORT_NOTIMPORTED . ", 3, 2, 0),
                       (" . PLUGIN_MASSOCSIMPORT_NOTIMPORTED . ", 4, 3, 0),
                       (" . PLUGIN_MASSOCSIMPORT_NOTIMPORTED . ", 5, 4, 0),
                       (" . PLUGIN_MASSOCSIMPORT_NOTIMPORTED . ", 6, 5, 0),
                       (" . PLUGIN_MASSOCSIMPORT_NOTIMPORTED . ", 7, 6, 0),
                       (" . PLUGIN_MASSOCSIMPORT_NOTIMPORTED . ", 8, 7, 0),
                       (" . PLUGIN_MASSOCSIMPORT_NOTIMPORTED . ", 8, 7, 0),
                       (" . PLUGIN_MASSOCSIMPORT_NOTIMPORTED . ", 10, 9, 0),
                       (" . PLUGIN_MASSOCSIMPORT_DETAIL . ", 5, 1, 0),
                       (" . PLUGIN_MASSOCSIMPORT_DETAIL . ", 2, 2, 0),
                       (" . PLUGIN_MASSOCSIMPORT_DETAIL . ", 3, 3, 0),
                       (" . PLUGIN_MASSOCSIMPORT_DETAIL . ", 4, 4, 0),
                       (" . PLUGIN_MASSOCSIMPORT_DETAIL . ", 6, 5, 0)";
      $DB->query($query);// or die($DB->error());

      $drop_fields = array (//Was not used, debug only...
                            "glpi_plugin_massocsimport_config" => "warn_if_not_imported",
                            "glpi_plugin_massocsimport_config" => "not_imported_threshold",
                            //Logging must always be enable !
                            "glpi_plugin_massocsimport_config" => "enable_logging",
                            "glpi_plugin_massocsimport_config" => "delete_empty_frequency");

      foreach ($drop_fields as $table => $field) {
         $migration->dropField($table, $field);
      }
   }
   $migration->executeMigration();
}


function plugin_ocsinventoryng_upgrademassocsimport13to14() {
   global $DB;

   $migration = new Migration(14);

   $migration->renameTable("glpi_plugin_massocsimport", "glpi_plugin_massocsimport_threads");

   $migration->changeField("glpi_plugin_massocsimport_threads", "ID", "id", 'autoincrement');
   $migration->changeField("glpi_plugin_massocsimport_threads", "thread_id", "threadid", 'integer');
   $migration->changeField("glpi_plugin_massocsimport_threads", "status", "status", 'integer');
   $migration->changeField("glpi_plugin_massocsimport_threads", "ocs_server_id", "ocsservers_id",
                           'integer', array('value' => 1));
   $migration->changeField("glpi_plugin_massocsimport_threads", "process_id", "processid",
                           'integer');
   $migration->changeField("glpi_plugin_massocsimport_threads", "noupdate_machines_number",
                           "notupdated_machines_number", 'integer');

   $migration->migrationOneTable("glpi_plugin_massocsimport_threads");

   $migration->addKey("glpi_plugin_massocsimport_threads", array("processid", "threadid"),
                      "process_thread");


   $migration->renameTable("glpi_plugin_massocsimport_config", "glpi_plugin_massocsimport_configs");

   $migration->dropField("glpi_plugin_massocsimport_config", "delete_frequency");
   $migration->dropField("glpi_plugin_massocsimport_config", "enable_logging");
   $migration->dropField("glpi_plugin_massocsimport_config", "delete_empty_frequency");
   $migration->dropField("glpi_plugin_massocsimport_config", "warn_if_not_imported");
   $migration->dropField("glpi_plugin_massocsimport_config", "delete_frequency");
   $migration->dropField("glpi_plugin_massocsimport_configs", "not_imported_threshold");

   $migration->changeField("glpi_plugin_massocsimport_configs", "ID", "id", 'autoincrement');
   $migration->changeField("glpi_plugin_massocsimport_configs", "thread_log_frequency",
                           "thread_log_frequency", 'integer', array('value' => 10));
   $migration->changeField("glpi_plugin_massocsimport_configs", "display_empty", "is_displayempty",
                           'int(1) NOT NULL default 1');
   $migration->changeField("glpi_plugin_massocsimport_configs", "default_ocs_server",
                           "ocsservers_id", 'integer', array('value' => -1));
   $migration->changeField("glpi_plugin_massocsimport_configs", "delay_refresh", "delay_refresh",
                           'integer');
   $migration->changeField("glpi_plugin_massocsimport_configs", "comments", "comment", 'text');


   $migration->changeField("glpi_plugin_massocsimport_details", "ID", "id", 'autoincrement');
   $migration->changeField("glpi_plugin_massocsimport_details", "process_id",
                           "plugin_massocsimport_threads_id", 'integer');
   $migration->changeField("glpi_plugin_massocsimport_details", "thread_id", "threadid", 'integer');
   $migration->changeField("glpi_plugin_massocsimport_details", "ocs_id", "ocsid", 'integer');
   $migration->changeField("glpi_plugin_massocsimport_details", "glpi_id", "computers_id",
                           'integer');
   $migration->changeField("glpi_plugin_massocsimport_details", "ocs_server_id",
                           "ocsservers_id", 'integer', array('value' => 1));

   $migration->migrationOneTable(glpi_plugin_massocsimport_details);
   $migration->addKey("glpi_plugin_massocsimport_details",
                      array("plugin_massocsimport_threads_id", "threadid"), "process_thread");


   $migration->renameTable("glpi_plugin_massocsimport_not_imported",
                           "glpi_plugin_massocsimport_notimported");

   $migration->changeField("glpi_plugin_massocsimport_notimported", "ID", "id", 'autoincrement');
   $migration->changeField("glpi_plugin_massocsimport_notimported", "ocs_id", "ocsid", 'integer');
   $migration->changeField("glpi_plugin_massocsimport_notimported", "ocs_server_id", "ocsservers_id",
                           'integer');
   $migration->changeField("glpi_plugin_massocsimport_notimported", "deviceid", "ocs_deviceid",
                           'string');



   $migration->changeField("glpi_plugin_massocsimport_servers", "ID", "id", 'autoincrement');
   $migration->changeField("glpi_plugin_massocsimport_servers", "ocs_server_id", "ocsservers_id",
                           'integer');
   $migration->changeField("glpi_plugin_massocsimport_servers", "max_ocs_id", "max_ocsid",
                           'int(11) DEFAULT NULL');
   $migration->changeField("glpi_plugin_massocsimport_servers", "max_glpi_date", "max_glpidate",
                           'datetime DEFAULT NULL');

   $migration->executeMigration();
}


function plugin_ocsinventoryng_massocsimport_upgrade14to15() {
   global $DB;

   $migration = new Migration(15);

   $migration->addField("glpi_plugin_massocsimport_threads", "not_unique_machines_number",
                        'integer');
   $migration->addField("glpi_plugin_massocsimport_threads", "link_refused_machines_number",
                        'integer');
   $migration->addField("glpi_plugin_massocsimport_threads", "entities_id", 'integer');
   $migration->addField("glpi_plugin_massocsimport_threads", "rules_id", 'text');

   $migration->addField("glpi_plugin_massocsimport_configs", "allow_ocs_update", 'bool');

   $migration->addField("glpi_plugin_massocsimport_notimported", "reason", 'integer');

   $query = "INSERT INTO `glpi_displaypreferences`
                    (`itemtype`, `num`, `rank`, `users_id`)
             VALUES ('PluginMassocsimportNotimported', 10, 9, 0)";
   $DB->queryOrDie($query, "1.5 insert into glpi_displaypreferences " .$DB->error());

   $migration->addField("glpi_plugin_massocsimport_notimported", "serial", 'string',
                        array('value' => ''));
   $migration->addField("glpi_plugin_massocsimport_notimported", "comment", "TEXT NOT NULL");
   $migration->addField("glpi_plugin_massocsimport_notimported", "rules_id", 'text');
   $migration->addField("glpi_plugin_massocsimport_notimported", "entities_id", 'integer');

   $migration->addField("glpi_plugin_massocsimport_details", "entities_id", 'integer');
   $migration->addField("glpi_plugin_massocsimport_details", "rules_id", 'text');

   $query = "SELECT id " .
            "FROM `glpi_notificationtemplates` " .
            "WHERE `itemtype`='PluginMassocsimportNotimported'";
   $result = $DB->query($query);
   if (!$DB->numrows($result)) {

      //Add template
      $query = "INSERT INTO `glpi_notificationtemplates` " .
               "VALUES (NULL, 'Computers not imported', 'PluginMassocsimportNotimported',
                        NOW(), '', '');";
      $DB->queryOrDie($query, $DB->error());
      $templates_id = $DB->insert_id();
      $query = "INSERT INTO `glpi_notificationtemplatetranslations` " .
               "VALUES(NULL, $templates_id, '', '##lang.notimported.action## : ##notimported.entity##'," .
               " '\r\n\n##lang.notimported.action## :&#160;##notimported.entity##\n\n" .
               "##FOREACHnotimported##&#160;\n##lang.notimported.reason## : ##notimported.reason##\n" .
               "##lang.notimported.name## : ##notimported.name##\n" .
               "##lang.notimported.deviceid## : ##notimported.deviceid##\n" .
               "##lang.notimported.tag## : ##notimported.tag##\n##lang.notimported.serial## : ##notimported.serial## \r\n\n" .
               " ##notimported.url## \n##ENDFOREACHnotimported## \r\n', '&lt;p&gt;##lang.notimported.action## :&#160;##notimported.entity##&lt;br /&gt;&lt;br /&gt;" .
               "##FOREACHnotimported##&#160;&lt;br /&gt;##lang.notimported.reason## : ##notimported.reason##&lt;br /&gt;" .
               "##lang.notimported.name## : ##notimported.name##&lt;br /&gt;" .
               "##lang.notimported.deviceid## : ##notimported.deviceid##&lt;br /&gt;" .
               "##lang.notimported.tag## : ##notimported.tag##&lt;br /&gt;" .
               "##lang.notimported.serial## : ##notimported.serial##&lt;/p&gt;\r\n&lt;p&gt;&lt;a href=\"##infocom.url##\"&gt;" .
               "##notimported.url##&lt;/a&gt;&lt;br /&gt;##ENDFOREACHnotimported##&lt;/p&gt;');";
      $DB->queryOrDie($query, $DB->error());

      $query = "INSERT INTO `glpi_notifications`
                VALUES (NULL, 'Computers not imported', 0, 'PluginMassocsimportNotimported',
                        'not_imported', 'mail',".$templates_id.", '', 1, 1, NOW());";
      $DB->queryOrDie($query, $DB->error());
   }
   $migration->executeMigration();
}


function plugin_ocsinventoryng_uninstall() {
   global $DB;

   $tables = array("glpi_plugin_ocsinventoryng_ocsservers",
                   "glpi_plugin_ocsinventoryng_ocslinks",
                   "glpi_plugin_ocsinventoryng_ocsadmininfoslinks",
                   "glpi_plugin_ocsinventoryng_profiles",
                   "glpi_plugin_ocsinventoryng_threads",
                   "glpi_plugin_ocsinventoryng_servers",
                   "glpi_plugin_ocsinventoryng_configs",
                   "glpi_plugin_ocsinventoryng_notimported",
                   "glpi_plugin_ocsinventoryng_details",
                   "glpi_plugin_ocsinventoryng_registrykeys");

   foreach($tables as $table) {
      $DB->query("DROP TABLE IF EXISTS `$table`;");
   }
   // Pourquoi vu que les anciennes tables de massocsimport sont renommees
   // et les tables en backup_ xxx ne sont jamais automatiquement supprimees
   /*
   $massoldtables = array ("glpi_plugin_mass_ocs_import",
                    "glpi_plugin_massocsimport",
                    "glpi_plugin_massocsimport_threads",
                    "glpi_plugin_mass_ocs_import_servers",
                    "glpi_plugin_massocsimport_servers",
                    "glpi_plugin_mass_ocs_import_config",
                    "glpi_plugin_massocsimport_config",
                    "glpi_plugin_massocsimport_configs",
                    "glpi_plugin_mass_ocs_import_not_imported",
                    "glpi_plugin_massocsimport_not_imported",
                    "glpi_plugin_massocsimport_notimported",
                    "glpi_plugin_massocsimport_details");

   foreach ($massoldtables as $massoldtable) {
      $query = "DROP TABLE IF EXISTS `$massoldtable`;";
      $DB->query($query) or die($DB->error());
   }
   */
   $tables_glpi = array("glpi_bookmarks", "glpi_displaypreferences",
                        "glpi_documents_items", "glpi_logs", "glpi_tickets");

   foreach ($tables_glpi as $table_glpi) {
      $DB->query("DELETE
                  FROM `".$table_glpi."`
                  WHERE `itemtype` IN ('PluginMassocsimportNotimported',
                                       'PluginMassocsimportDetail',
                                       'PluginOcsinventoryngOcsServer',
                                       'PluginOcsinventoryngNotimported',
                                       'PluginOcsinventoryngDetail')");
   }
   $query = "DELETE
             FROM `glpi_alerts`
             WHERE `itemtype` IN ('PluginMassocsimportNotimported',
                                  'PluginOcsinventoryngNotimported')";
   $DB->queryOrDie($query, $DB->error());

   $notification = new Notification();
   foreach (getAllDatasFromTable($notification->getTable(),
                                 "`itemtype` IN ('PluginMassocsimportNotimported',
                                                 'PluginOcsinventoryngNotimported')") as $data) {
      $notification->delete($data);
   }
   $template = new NotificationTemplate();
   foreach (getAllDatasFromTable($template->getTable(),
                                 "`itemtype` IN ('PluginMassocsimportNotimported',
                                                 'PluginOcsinventoryngNotimported')") as $data) {
      $template->delete($data);
   }

   $cron = new CronTask;
   if ($cron->getFromDBbyName('PluginMassocsimportThread', 'CleanOldThreads')) {
      // creation du cron - param = duree de conservation
      CronTask::Unregister('massocsimport');
   }
   if ($cron->getFromDBbyName('PluginOcsinventoryngThread', 'CleanOldThreads')) {
      // creation du cron - param = duree de conservation
      CronTask::Unregister('ocsinventoryng');
   }

   return true;
}


/**
 * Define dropdown relations
**/
function plugin_ocsinventoryngs_getDatabaseRelations() {

   $plugin = new Plugin();

   if ($plugin->isActivated("ocsinventoryng")) {
      return array("glpi_plugin_ocsinventoryng_ocsservers"
                     => array("glpi_plugin_ocsinventoryng_ocslinks"
                                                         => "plugin_ocsinventoryng_ocsservers_id",
                              "glpi_plugin_ocsinventoryng_ocsadmininfoslinks"
                                                         => "plugin_ocsinventoryng_ocsservers_id"),

                   "glpi_entities"
                     => array("glpi_plugin_ocsinventoryng_ocslinks" => "entities_id"),

                   "glpi_computers"
                     => array("glpi_plugin_ocsinventoryng_ocslinks"     => "computers_id",
                              "glpi_plugin_ocsinventoryng_registrykeys" => "computers_id"),

                   "glpi_profiles"
                     => array("glpi_plugin_ocsinventoryng_profiles" => "profiles_id"),

                   "glpi_states"
                     => array("glpi_plugin_ocsinventoryng_ocsservers" => "states_id_default"));
   }
   return array ();
}


function plugin_ocsinventoryng_postinit() {
   global $CFG_GLPI, $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['pre_item_purge']['ocsinventoryng']
                              = array('Profile' =>  array('PluginOcsinventoryngProfile',
                                                          'purgeProfiles'));

   $PLUGIN_HOOKS['pre_item_add']['ocsinventoryng']    = array();
   $PLUGIN_HOOKS['item_update']['ocsinventoryng']     = array();

   foreach (PluginOcsinventoryngOcsServer::getTypes(true) as $type) {

      $PLUGIN_HOOKS['pre_item_add']['ocsinventoryng'][$type]
         = array('Computer_Item' => array('PluginOcsinventoryngOcslink', 'addComputer_Item'));

      $PLUGIN_HOOKS['item_update']['ocsinventoryng'][$type]
         = array('Computer' => array('PluginOcsinventoryngOcslink', 'updateComputer'));

      $PLUGIN_HOOKS['pre_item_purge']['ocsinventoryng'][$type]
         = array('Computer'      => array('PluginOcsinventoryngOcslink', 'purgeComputer'),
                 'Computer_Item' => array('PluginOcsinventoryngOcslink', 'purgeComputer_Item'));


      CommonGLPI::registerStandardTab($type, 'PluginOcsinventoryngOcsServer');
   }
}

/**
 * @param $type
**/
function plugin_ocsinventoryng_MassiveActions($type) {

   switch ($type) {
      case 'PluginOcsinventoryngNotimported' :
         $actions = array ();
         $actions["plugin_ocsinventoryng_replayrules"] = __("Restart import");
         $actions["plugin_ocsinventoryng_import"]      = __("Import in the entity");

         if (isset ($_POST['target'])
             && $_POST['target'] == getItemTypeFormURL('PluginOcsinventoryngNotimported')) {

            $actions["plugin_ocsinventoryng_link"]
                                          = __('Link computer whith another one present in GLPI');
         }
         $plugin = new Plugin;
         if ($plugin->isActivated("uninstall")) {
            $actions["plugin_ocsinventoryng_delete"]   = __('Delete computer in OCS');
         }
         return $actions;

      case 'Computer' :
         if (plugin_ocsinventoryng_haveRight("ocsng","w")
             || plugin_ocsinventoryng_haveRight("sync_ocsng","w")) {

            return array(// Specific one
                         "plugin_ocsinventoryng_force_ocsng_update"
                                                               => __('Force synchronization OCSNG'),
                         "plugin_ocsinventoryng_unlock_ocsng_field"      => _('(Unlock fields'),
                         "plugin_ocsinventoryng_unlock_ocsng_monitor"    => __('Unlock monitors'),
                         "plugin_ocsinventoryng_unlock_ocsng_peripheral" => __('Unlock peripherals'),
                         "plugin_ocsinventoryng_unlock_ocsng_printer"    => __('Unlock printers'),
                         "plugin_ocsinventoryng_unlock_ocsng_software"   => __('Unlock software'),
                         "plugin_ocsinventoryng_unlock_ocsng_ip"         => __('Unlock IP'),
                         "plugin_ocsinventoryng_unlock_ocsng_disk"       => __('Unclok volumes'));
         }
         break;
   }
   return array ();
}


/**
 * @param $options   array
*/
function plugin_ocsinventoryng_MassiveActionsDisplay($options=array()) {

   switch ($options['itemtype']) {
      case 'PluginOcsinventoryngNotimported' :
         switch ($options['action']) {
            case "plugin_ocsinventoryng_import" :
               Entity::dropdown(array('name' => 'entity'));
               break;

            case "plugin_ocsinventoryng_link" :
               Computer::dropdown(array('name' => 'computers_id'));
               break;

            case "plugin_ocsinventoryng_replayrules" :
            case "plugin_ocsinventoryng_delete" :
               break;
         }
         echo "&nbsp;<input type='submit' name='massiveaction' class='submit' " .
              "value='"._sx('button', 'Post')."'>";
         break;

      case 'Computer' :
         switch ($options['action']) {
            case "plugin_ocsinventoryng_force_ocsng_update" :
               echo "<input type='submit' name='massiveaction' class='submit' value='".
                      _sx('button', 'Post')."'>\n";
               break;

            case "plugin_ocsinventoryng_unlock_ocsng_field" :
               $fields['all'] = __('All');
               $fields       += PluginOcsinventoryngOcsServer::getLockableFields();
               Dropdown::showFromArray("field", $fields);
               echo "<br><br><input type='submit' name='massiveaction' class='submit' value='".
                              _sx('button', 'Post')."'>";
               break;

            case "plugin_ocsinventoryng_unlock_ocsng_monitor" :
            case "plugin_ocsinventoryng_unlock_ocsng_peripheral" :
            case "plugin_ocsinventoryng_unlock_ocsng_software" :
            case "plugin_ocsinventoryng_unlock_ocsng_printer" :
            case "plugin_ocsinventoryng_unlock_ocsng_disk" :
            case "plugin_ocsinventoryng_unlock_ocsng_ip" :
               echo "<input type='submit' name='massiveaction' class='submit' value='".
                      __s('Unlock')."'>";
               break;
         }
   }
   return "";
}


/**
 * @param $data   array
**/
function plugin_ocsinventoryng_MassiveActionsProcess($data) {
   global $CFG_GLPI, $DB, $REDIRECT;

   $notimport = new PluginOcsinventoryngNotimported();
   switch ($data["action"]) {
      case "plugin_ocsinventoryng_import" :
         foreach ($data["item"] as $key => $val) {
            if ($val == 1) {
               PluginOcsinventoryngNotimported::computerImport(array('id'     => $key,
                                                                     'force'  => true,
                                                                     'entity' => $data['entity']));
            }
         }
         break;

      case "plugin_ocsinventoryng_replayrules" :
         foreach ($data["item"] as $key => $val) {
            if ($val == 1) {
               PluginOcsinventoryngNotimported::computerImport(array('id' => $key));
            }
         }
         break;

      case "plugin_ocsinventoryng_delete" :
         $plugin = new Plugin();
         if ($plugin->isActivated("uninstall")) {
            foreach ($data["item"] as $key => $val) {
               if ($val == 1) {
                  $notimport->deleteNotImportedComputer($key);
               }
            }
         }
         break;
      case "plugin_ocsinventoryng_unlock_ocsng_field" :
         $fields = PluginOcsinventoryngOcsServer::getLockableFields();
         if ($_POST['field'] == 'all' || isset($fields[$_POST['field']])) {
            foreach ($_POST["item"] as $key => $val) {
               if ($val == 1) {
                  if ($item->can($key,'w')) {
                     if ($_POST['field'] == 'all') {
                        if (PluginOcsinventoryngOcsServer::replaceOcsArray($key, array(),
                                                                           "computer_update")) {
                           $nbok++;
                        } else {
                           $nbko++;
                        }
                     } else {
                        if (PluginOcsinventoryngOcsServer::deleteInOcsArray($key, $_POST['field'],
                                                                            "computer_update",
                                                                            true)) {
                           $nbok++;
                        } else {
                           $nbko++;
                        }
                     }
                  } else {
                     $nbnoright++;
                  }
               }
            }
         }
         break;

      case "plugin_ocsinventoryng_unlock_ocsng_monitor" :
      case "plugin_ocsinventoryng_unlock_ocsng_printer" :
      case "plugin_ocsinventoryng_unlock_ocsng_peripheral" :
      case "plugin_ocsinventoryng_unlock_ocsng_software" :
      case "plugin_ocsinventoryng_unlock_ocsng_ip" :
      case "plugin_ocsinventoryng_unlock_ocsng_disk" :
         foreach ($_POST["item"] as $key => $val) {
            if ($val == 1) {
               if ($tiem->can($key, 'w')) {
                  switch ($_POST["action"]) {
                     case "plugin_ocsinventoryng_unlock_ocsng_monitor" :
                        if (PluginOcsinventoryngOcsServer::unlockItems($key, "import_monitor")) {
                           $nbok++;
                        } else {
                           $nbko++;
                        }
                        break;

                     case "plugin_ocsinventoryng_unlock_ocsng_printer" :
                        if (PluginOcsinventoryngOcsServer::unlockItems($key, "import_printer")) {
                           $nbok++;
                        } else {
                           $nbko++;
                        }
                        break;

                     case "plugin_ocsinventoryng_unlock_ocsng_peripheral" :
                        if (PluginOcsinventoryngOcsServer::unlockItems($key, "import_peripheral")) {
                           $nbok++;
                        } else {
                           $nbko++;
                        }
                        break;

                     case "plugin_ocsinventoryng_unlock_ocsng_software" :
                        if (PluginOcsinventoryngOcsServer::unlockItems($key, "import_software")) {
                           $nbok++;
                        } else {
                           $nbko++;
                        }
                        break;

                     case "plugin_ocsinventoryng_unlock_ocsng_ip" :
                        if (PluginOcsinventoryngOcsServer::unlockItems($key, "import_ip")) {
                           $nbok++;
                        } else {
                           $nbko++;
                        }
                        break;

                     case "plugin_ocsinventoryng_unlock_ocsng_disk" :
                        if (PluginOcsinventoryngOcsServer::unlockItems($key, "import_disk")) {
                           $nbok++;
                        } else {
                           $nbko++;
                        }
                        break;

                  }
               } else {
                  $nbnoright++;
               }
            }
         }
         break;

      case "plugin_ocsinventoryng_force_ocsng_update" :
         // First time
         if (!isset($_GET['multiple_actions'])) {
            $_SESSION['glpi_massiveaction']['POST']      = $_POST;
            $_SESSION['glpi_massiveaction']['REDIRECT']  = $REDIRECT;
            $_SESSION['glpi_massiveaction']['items']     = array();
            foreach ($_POST["item"] as $key => $val) {
               if ($val == 1) {
                  $_SESSION['glpi_massiveaction']['items'][$key] = $key;
               }
            }
            $_SESSION['glpi_massiveaction']['item_count']
                  = count($_SESSION['glpi_massiveaction']['items']);
            $_SESSION['glpi_massiveaction']['items_ok']        = 0;
            $_SESSION['glpi_massiveaction']['items_ko']        = 0;
            $_SESSION['glpi_massiveaction']['items_nbnoright'] = 0;
            Html::redirect($_SERVER['PHP_SELF'].'?multiple_actions=1');

         } else {
            if (count($_SESSION['glpi_massiveaction']['items']) > 0) {
               $key = array_pop($_SESSION['glpi_massiveaction']['items']);
               if ($item->can($key,'w')) {
                  //Try to get the OCS server whose machine belongs
                  $query = "SELECT `plugin_ocsinventoryng_ocsservers_id`, `id`
                            FROM `glpi_plugin_ocsinventoryng_ocslinks`
                            WHERE `computers_id` = '".$key."'";
                  $result = $DB->query($query);
                  if ($DB->numrows($result) == 1) {
                     $data = $DB->fetch_assoc($result);
                     if ($data['plugin_ocsinventoryng_ocsservers_id'] != -1) {
                        //Force update of the machine
                        PluginOcsinventoryngOcsServer::updateComputer($data['id'],
                                                                      $data['plugin_ocsinventoryng_ocsservers_id'],
                                                                      1, 1);
                        $_SESSION['glpi_massiveaction']['items_ok']++;
                     } else {
                        $_SESSION['glpi_massiveaction']['items_ko']++;
                     }
                  } else {
                     $_SESSION['glpi_massiveaction']['items_ko']++;
                  }
               } else {
                  $_SESSION['glpi_massiveaction']['items_nbnoright']++;
               }
               Html::redirect($_SERVER['PHP_SELF'].'?multiple_actions=1');
            } else {
               $REDIRECT  = $_SESSION['glpi_massiveaction']['REDIRECT'];
               $nbok      = $_SESSION['glpi_massiveaction']['items_ok'];
               $nbko      = $_SESSION['glpi_massiveaction']['items_ko'];
               $nbnoright = $_SESSION['glpi_massiveaction']['items_nbnoright'];
               unset($_SESSION['glpi_massiveaction']);
            }
         }
         break;
   }
}


/**
 * @param $itemtype
**/
function plugin_ocsinventoryng_getAddSearchOptions($itemtype) {

    $sopt = array();

   if ($itemtype == 'Computer') {
      if (plugin_ocsinventoryng_haveRight("ocsng","r")) {

         $sopt[102]['table']         = 'glpi_plugin_ocsinventoryng_ocslinks';
         $sopt[102]['field']         = 'last_update';
         $sopt[102]['name']          = __('GLPI import date');
         $sopt[102]['datatype']      = 'datetime';
         $sopt[102]['massiveaction'] = false;
         $sopt[102]['joinparams']    = array('jointype' => 'child');

         $sopt[103]['table']         = 'glpi_plugin_ocsinventoryng_ocslinks';
         $sopt[103]['field']         = 'last_ocs_update';
         $sopt[103]['name']          = __('Last OCS inventory');
         $sopt[103]['datatype']      = 'datetime';
         $sopt[103]['massiveaction'] = false;
         $sopt[103]['joinparams']    = array('jointype' => 'child');


         $sopt[101]['table']         = 'glpi_plugin_ocsinventoryng_ocslinks';
         $sopt[101]['field']         = 'use_auto_update';
         $sopt[101]['linkfield']     = '_auto_update_ocs'; // update through compter update process
         $sopt[101]['name']          = __('Automatic update OCSNG');
         $sopt[101]['datatype']      = 'bool';
         $sopt[101]['joinparams']    = array('jointype' => 'child');

         $sopt[104]['table']         = 'glpi_plugin_ocsinventoryng_ocslinks';
         $sopt[104]['field']         = 'ocs_agent_version';
         $sopt[104]['name']          = __('Inventory agent');
         $sopt[104]['massiveaction'] = false;
         $sopt[104]['joinparams']    = array('jointype' => 'child');

         $sopt[105]['table']         = 'glpi_plugin_ocsinventoryng_ocslinks';
         $sopt[105]['field']         = 'tag';
         $sopt[105]['name']          = __('TAG OCSNG');
         $sopt[105]['datatype']      = 'string';
         $sopt[105]['massiveaction'] = false;
         $sopt[105]['joinparams']    = array('jointype' => 'child');

         $sopt[106]['table']         = 'glpi_plugin_ocsinventoryng_ocslinks';
         $sopt[106]['field']         = 'ocsid';
         $sopt[106]['name']          = __('OCS ID');
         $sopt[106]['datatype']      = 'number';
         $sopt[106]['massiveaction'] = false;
         $sopt[106]['joinparams']    = array('jointype' => 'child');

         $sopt['registry']           = __('Registry');

         $sopt[110]['table']         = 'glpi_plugin_ocsinventoryng_registrykeys';
         $sopt[110]['field']         = 'value';
         $sopt[110]['name']          = __('Registry: Key/Value');
         $sopt[110]['forcegroupby']  = true;
         $sopt[110]['massiveaction'] = false;
         $sopt[110]['joinparams']    = array('jointype' => 'child');

         $sopt[111]['table']         = 'glpi_plugin_ocsinventoryng_registrykeys';
         $sopt[111]['field']         = 'ocs_name';
         $sopt[111]['name']          = __('Registry: OCSNG name');
         $sopt[111]['forcegroupby']  = true;
         $sopt[111]['massiveaction'] = false;
         $sopt[111]['joinparams']    = array('jointype' => 'child');
      }
   }
   return $sopt;
}


/**
 * @param $type
 * @param $ID
 * @param $data
 * @param $num
**/
function plugin_ocsinventoryng_displayConfigItem($type, $ID, $data, $num) {

   $searchopt  = &Search::getOptions($type);
   $table      = $searchopt[$ID]["table"];
   $field      = $searchopt[$ID]["field"];

   switch ($table.'.'.$field) {
      case "glpi_plugin_ocsinventoryng_ocslinks.last_update" :
      case "glpi_plugin_ocsinventoryng_ocslinks.last_ocs_update" :
         return " class='center'";
   }
   return "";
}


/**
 * @param $type
 * @param $id
 * @param $num
**/
function plugin_ocsinventoryng_addSelect($type, $id, $num) {

   $searchopt  = &Search::getOptions($type);
   $table      = $searchopt[$id]["table"];
   $field      = $searchopt[$id]["field"];

   $out = "`$table`.`$field` AS ITEM_$num,
           `$table`.`ocsid` AS ocsid,
           `$table`.`plugin_ocsinventoryng_ocsservers_id` AS plugin_ocsinventoryng_ocsservers_id, ";

   if ($num == 0) {
      switch ($type) {
         case 'PluginOcsinventoryngNotimported' :
            return $out;

         case 'PluginOcsinventoryngDetail' :
            $out .= "`$table`.`plugin_ocsinventoryng_threads_id`,
                     `$table`.`threadid`, ";
            return $out;
      }
      return "";
   }
}


/**
 * @param $link
 * @param $nott
 * @param $type
 * @param $ID
 * @param $val
**/
function plugin_ocsinventoryng_addWhere($link, $nott, $type, $ID, $val) {

   $searchopt  = &Search::getOptions($type);
   $table      = $searchopt[$ID]["table"];
   $field      = $searchopt[$ID]["field"];

   $SEARCH     = makeTextSearch($val,$nott);
    switch ($table.".".$field) {
         case "glpi_plugin_ocsinventoryng_details.action" :
               return $link." `$table`.`$field` = '$val' ";
    }
   return "";
}


/**
 * @param $type
 * @param $id
 * @param $data
 * @param $num
**/
function plugin_ocsinventoryng_giveItem($type, $id, $data, $num) {
   global $CFG_GLPI, $DB;

   $searchopt  = &Search::getOptions($type);
   $table      = $searchopt[$id]["table"];
   $field      = $searchopt[$id]["field"];

   switch ("$table.$field") {
      case "glpi_plugin_ocsinventoryng_details.action" :
         $detail = new PluginOcsinventoryngDetail();
         return $detail->giveActionNameByActionID($data["ITEM_$num"]);

      case "glpi_plugin_ocsinventoryng_details.computers_id" :
         $comp = new Computer();
         $comp->getFromDB($data["ITEM_$num"]);
         return "<a href='".getItemTypeFormURL('Computer')."?id=".$data["ITEM_$num"]."'>".
                  $comp->getName()."</a>";

      case "glpi_plugin_ocsinventoryng_details.plugin_ocsinventoryng_ocsservers_id" :
         $ocs = new PluginOcsinventoryngOcsServer();
         $ocs->getFromDB($data["ITEM_$num"]);
         return "<a href='".getItemTypeFormURL('PluginOcsinventoryngOcsServer')."?id=".
                  $data["ITEM_$num"]."'>".$ocs->getName()."</a>";

      case "glpi_plugin_ocsinventoryng_details.rules_id" :
         $detail = new PluginOcsinventoryngDetail();
         $detail->getFromDB($data['id']);
         return PluginOcsinventoryngNotimported::getRuleMatchedMessage($detail->fields['rules_id']);

      case "glpi_plugin_ocsinventoryng_notimported.reason" :
         return PluginOcsinventoryngNotimported::getReason($data["ITEM_$num"]);
   }
   return '';
}


/**
 * @param $params array
**/
function plugin_ocsinventoryng_searchOptionsValues($params=array()) {

   switch($params['searchoption']['field']) {
      case "action":
         PluginOcsinventoryngDetail::showActions($params['name'],$params['value']);
         return true;
   }
   return false;
}
?>