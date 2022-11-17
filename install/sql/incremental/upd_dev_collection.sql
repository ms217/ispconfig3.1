UPDATE `country` SET `eu` = 'n' WHERE `iso` = 'GB';
ALTER TABLE `client` CHANGE `id_rsa` `id_rsa` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '';
