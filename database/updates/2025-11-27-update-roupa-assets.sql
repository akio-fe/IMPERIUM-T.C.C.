-- Atualiza os caminhos de modelos e imagens para refletir a nova estrutura de pastas
USE `imperium`;

UPDATE `roupa`
SET `RoupaModelUrl` = CONCAT('/storage/models/', SUBSTRING(`RoupaModelUrl`, LENGTH('/models/') + 1))
WHERE `RoupaModelUrl` LIKE '/models/%';

UPDATE `roupa`
SET `RoupaImgUrl` = CONCAT('public/assets/img/catalog/', SUBSTRING(`RoupaImgUrl`, LENGTH('img/') + 1))
WHERE `RoupaImgUrl` LIKE 'img/%';
