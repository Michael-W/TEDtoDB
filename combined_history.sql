
--
-- Table structure for table `combined_history`
--

DROP TABLE IF EXISTS `combined_history`;
CREATE TABLE IF NOT EXISTS `combined_history` (
  `ted_ts` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  `mtu` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '1-4',
  `hist` enum('s','m','h','d','o') NOT NULL DEFAULT 'm' COMMENT 's, m, h, d, o',
  `pwr` decimal(8,3) NOT NULL DEFAULT '0.000' COMMENT 'kWh',
  `cost` decimal(10,6) NOT NULL DEFAULT '0.000000' COMMENT '$',
  `ins_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ted_ts`,`mtu`,`hist`),
  KEY `IX_ins_ts` (`ins_ts`)
) ENGINE=MyISAM DEFAULT CHARSET=ascii;
