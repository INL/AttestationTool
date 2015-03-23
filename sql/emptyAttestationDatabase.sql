-- MySQL dump 10.11
--
-- Server version	5.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `attestations`
--

DROP TABLE IF EXISTS `attestations`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `attestations` (
  `id` bigint(20) unsigned NOT NULL auto_increment,
  `quotationId` int(11) NOT NULL default '0',
  `onset` int(11) NOT NULL default '0',
  `offset` int(11) default NULL,
  `reliability` decimal(5,1) default NULL,
  `wordForm` varchar(100) character set utf8 collate utf8_bin NOT NULL default '',
  `typeId` int(3) unsigned default '1',
  `error` int(1) default '0',
  `dubious` int(1) default '0',
  `elliptical` int(1) unsigned default '0',
  `comment` varchar(255) NOT NULL default '',
  `tokenId` varchar(255),
  PRIMARY KEY  (`id`),
  UNIQUE KEY `quotationIdOnsetKey` (`quotationId`,`onset`),
  KEY `quotationIdKey` (`quotationId`),
  KEY `wordFormKey` (`wordForm`)
) ENGINE=MyISAM AUTO_INCREMENT=74054 DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `groupAttestations`
--

DROP TABLE IF EXISTS `groupAttestations`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `groupAttestations` (
  `id` bigint(20) unsigned NOT NULL,
  `attestationId` bigint(20) unsigned NOT NULL,
  `pos` bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (`id`,`attestationId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `lemmata`
--

DROP TABLE IF EXISTS `lemmata`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `lemmata` (
  `id` int(32) NOT NULL auto_increment,
  `lemma` varchar(100) character set utf8 collate utf8_bin NOT NULL default '',
  `partOfSpeech` varchar(31) NOT NULL default '',
  `externalLemmaId` varchar(255) default NULL,
  `initialVariants` varchar(200) character set utf8 collate utf8_bin default NULL,
  `revisionDate` datetime default NULL,
  `revisorId` int(10) default NULL,
  `comment` varchar(255) default NULL,
  `marked` tinyint(1) default '0',
  PRIMARY KEY  (`id`),
  KEY `externalLemmaId` (`externalLemmaId`),
  KEY `lemma` (`lemma`),
  KEY `revisorId` (`revisorId`),
  KEY `revisionDate` (`revisionDate`)
) ENGINE=MyISAM AUTO_INCREMENT=7349 DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `quotations`
--

DROP TABLE IF EXISTS `quotations`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `quotations` (
  `id` int(11) NOT NULL auto_increment,
  `quotation` longtext,
  `tokenizedQuotation` longtext,
  `quotationSectionId` varchar(200) default NULL,
  `dateFrom` int(11) default NULL,
  `dateTo` int(11) default NULL,
  `specialAttention` int(1) default '0',
  `unfortunate` int(1) default '0',
  `lemmaId` int(32) default NULL,
  PRIMARY KEY  (`id`),
  KEY `quotationSectionId` (`quotationSectionId`),
  KEY `dateFrom` (`dateFrom`),
  KEY `lemmaId` (`lemmaId`),
  KEY `dateTo` (`dateTo`)
) ENGINE=MyISAM AUTO_INCREMENT=60727 DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `revisors`
--

DROP TABLE IF EXISTS `revisors`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `revisors` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(20) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `types`
--

DROP TABLE IF EXISTS `types`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `types` (
  `id` int(3) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `color` varchar(7) default NULL,
  `shortcut` varchar(45) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2013-11-21 10:59:11
