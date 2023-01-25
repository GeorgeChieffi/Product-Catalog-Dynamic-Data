-- <?php die('This is not a program file.'); exit; ?>


--
-- Table structure for table `#TABLE_PREFIX#_single_record_sample`
--

DROP TABLE IF EXISTS `#TABLE_PREFIX#_single_record_sample`;

CREATE TABLE `#TABLE_PREFIX#_single_record_sample` (
  `num` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `createdDate` datetime NOT NULL,
  `createdByUserNum` int(10) unsigned NOT NULL,
  `updatedDate` datetime NOT NULL,
  `updatedByUserNum` int(10) unsigned NOT NULL,
  `title` mediumtext,
  `content` mediumtext,
  `date` datetime NOT NULL,
  `status` mediumtext,
  `checked` tinyint(1) unsigned NOT NULL,
  PRIMARY KEY (`num`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;


--
-- Dumping data for table `#TABLE_PREFIX#_single_record_sample`
--

INSERT INTO `#TABLE_PREFIX#_single_record_sample` VALUES("1","2015-10-13 12:39:52","1","2015-10-13 13:33:53","1","Sample Record Title","<p>Eshorta eshekitathe ofe sodawtaw afegel coosona tirolig ofedadal rere arisi cerise raheror athel forseja awtothe rolorthel jafe cutut lonurnap tam ethumen efeth newebot ehoira isado ujemorv rekedeh ahiluse damor ehe arethe torenof esiturge lishek sisute itabo rabikomus ene chethutec sheti losoumaf nulesid botefour relala enabi hasisaseb exe ralenob agebaw teces bedut ni bereleh shoigu enatouto enasadou eroikegac hithu farupi toset ubaras awhopu tecul udile ahawcuk sare atafar loleh ihet ciduthi aser atebe ichin luta nihot.</p>","2015-10-13 00:00:00","Approved","1");

-- Dump completed on 2015-10-13 17:05:46 -0700

