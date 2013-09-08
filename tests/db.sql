CREATE TABLE IF NOT EXISTS `model1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `int1` int(11),
  `str1` varchar(64),
  `model2_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `model2_id` (`model2_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `model2` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `int1` int(11) DEFAULT NULL,
  `str1` varchar(128),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

ALTER TABLE `model1`
  ADD CONSTRAINT `model1_ibfk_1` FOREIGN KEY (`model2_id`) REFERENCES `model2` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

