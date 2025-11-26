-- --------------------------------------------------------
-- Servidor:                     127.0.0.1
-- Versão do servidor:           8.0.36 - MySQL Community Server - GPL
-- OS do Servidor:               Win64
-- HeidiSQL Versão:              12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Copiando estrutura do banco de dados para imperium
DROP DATABASE IF EXISTS `imperium`;
CREATE DATABASE IF NOT EXISTS `imperium` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `imperium`;

-- Copiando estrutura para tabela imperium.carrinho
DROP TABLE IF EXISTS `carrinho`;
CREATE TABLE IF NOT EXISTS `carrinho` (
  `CarProID` int NOT NULL AUTO_INCREMENT,
  `CarProQtd` int NOT NULL,
  `CarProPreco` decimal(10,2) NOT NULL,
  `RoupaId` int NOT NULL,
  `UsuId` int NOT NULL,
  PRIMARY KEY (`CarProID`),
  KEY `UsuId` (`UsuId`),
  KEY `RoupaId` (`RoupaId`),
  CONSTRAINT `FK_CarrinhoProduto_3` FOREIGN KEY (`RoupaId`) REFERENCES `roupa` (`RoupaId`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_carrinhoproduto_usuario` FOREIGN KEY (`UsuId`) REFERENCES `usuario` (`UsuId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela imperium.carrinho: ~0 rows (aproximadamente)
DELETE FROM `carrinho`;

-- Copiando estrutura para tabela imperium.catroupa
DROP TABLE IF EXISTS `catroupa`;
CREATE TABLE IF NOT EXISTS `catroupa` (
  `CatRId` int NOT NULL AUTO_INCREMENT,
  `CatRSexo` varchar(50) NOT NULL,
  `CatRTipo` varchar(100) NOT NULL,
  `CatRSessao` varchar(100) NOT NULL,
  PRIMARY KEY (`CatRId`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela imperium.catroupa: ~6 rows (aproximadamente)
DELETE FROM `catroupa`;
INSERT INTO `catroupa` (`CatRId`, `CatRSexo`, `CatRTipo`, `CatRSessao`) VALUES
	(1, 'Unissex', 'Calçados', 'Sneakers & Tênis'),
	(2, 'Unissex', 'Calças', 'Bottoms'),
	(3, 'Unissex', 'Blusas', 'Casacos & Hoodies'),
	(4, 'Unissex', 'Camisas', 'T-shirts & Shirts'),
	(5, 'Unissex', 'Conjuntos', 'Sets & Matching Fits'),
	(6, 'Unissex', 'Acessorios', 'Bonés & Outros');

-- Copiando estrutura para tabela imperium.enderecoentrega
DROP TABLE IF EXISTS `enderecoentrega`;
CREATE TABLE IF NOT EXISTS `enderecoentrega` (
  `EndEntId` int NOT NULL AUTO_INCREMENT,
  `EndEntRef` varchar(50) NOT NULL,
  `EndEntRua` varchar(150) NOT NULL,
  `EndEntCep` varchar(9) NOT NULL,
  `EndEntNum` int NOT NULL,
  `EndEntBairro` varchar(100) NOT NULL,
  `EndEntCid` varchar(150) NOT NULL,
  `EndEntEst` varchar(2) NOT NULL,
  `EndEntComple` varchar(100) DEFAULT NULL,
  `UsuId` int NOT NULL,
  PRIMARY KEY (`EndEntId`),
  KEY `FK_EnderecoEntrega_2` (`UsuId`),
  CONSTRAINT `FK_EnderecoEntrega_2` FOREIGN KEY (`UsuId`) REFERENCES `usuario` (`UsuId`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela imperium.enderecoentrega: ~0 rows (aproximadamente)
DELETE FROM `enderecoentrega`;

-- Copiando estrutura para tabela imperium.estoque
DROP TABLE IF EXISTS `estoque`;
CREATE TABLE IF NOT EXISTS `estoque` (
  `EstoId` int NOT NULL AUTO_INCREMENT,
  `EstoNum` varchar(15) NOT NULL,
  `EstoEst` varchar(50) NOT NULL,
  `EstoCid` varchar(50) NOT NULL,
  `EstoRua` varchar(150) NOT NULL,
  `EstoBairro` varchar(100) NOT NULL,
  `EstoCep` varchar(9) NOT NULL,
  `EstoDesc` varchar(150) NOT NULL,
  PRIMARY KEY (`EstoId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela imperium.estoque: ~0 rows (aproximadamente)
DELETE FROM `estoque`;

-- Copiando estrutura para tabela imperium.estoqueproduto
DROP TABLE IF EXISTS `estoqueproduto`;
CREATE TABLE IF NOT EXISTS `estoqueproduto` (
  `EstProId` int NOT NULL AUTO_INCREMENT,
  `EstProQtd` int NOT NULL DEFAULT '0',
  `EstProDataAtu` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `EstoId` int NOT NULL,
  `RoupaId` int NOT NULL,
  PRIMARY KEY (`EstProId`),
  UNIQUE KEY `idx_esto_roupa` (`EstoId`,`RoupaId`),
  KEY `RoupaId` (`RoupaId`),
  CONSTRAINT `estoqueproduto_ibfk_1` FOREIGN KEY (`EstoId`) REFERENCES `estoque` (`EstoId`),
  CONSTRAINT `estoqueproduto_ibfk_2` FOREIGN KEY (`RoupaId`) REFERENCES `roupa` (`RoupaId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela imperium.estoqueproduto: ~0 rows (aproximadamente)
DELETE FROM `estoqueproduto`;

-- Copiando estrutura para tabela imperium.favorito
DROP TABLE IF EXISTS `favorito`;
CREATE TABLE IF NOT EXISTS `favorito` (
  `FavProId` int NOT NULL AUTO_INCREMENT,
  `FavProData` datetime NOT NULL,
  `RoupaId` int NOT NULL,
  `UsuId` int NOT NULL,
  PRIMARY KEY (`FavProId`),
  KEY `UsuId` (`UsuId`),
  KEY `FK_FavoritoProduto_2` (`RoupaId`),
  CONSTRAINT `FK_FavoritoProduto_2` FOREIGN KEY (`RoupaId`) REFERENCES `roupa` (`RoupaId`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_favoritoproduto_usuario` FOREIGN KEY (`UsuId`) REFERENCES `usuario` (`UsuId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela imperium.favorito: ~0 rows (aproximadamente)
DELETE FROM `favorito`;

-- Copiando estrutura para tabela imperium.funcionario
DROP TABLE IF EXISTS `funcionario`;
CREATE TABLE IF NOT EXISTS `funcionario` (
  `UsuId` int NOT NULL,
  `FunSalario` decimal(10,2) NOT NULL,
  `FunDataAdmissao` date NOT NULL,
  `FunCargo` varchar(100) NOT NULL,
  PRIMARY KEY (`UsuId`),
  CONSTRAINT `funcionario_ibfk_1` FOREIGN KEY (`UsuId`) REFERENCES `usuario` (`UsuId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela imperium.funcionario: ~0 rows (aproximadamente)
DELETE FROM `funcionario`;

-- Copiando estrutura para tabela imperium.pagamento
DROP TABLE IF EXISTS `pagamento`;
CREATE TABLE IF NOT EXISTS `pagamento` (
  `PagId` int NOT NULL AUTO_INCREMENT,
  `PagDataHora` datetime NOT NULL,
  `PagValor` decimal(10,2) NOT NULL,
  `PagTransacaoCod` varchar(255) DEFAULT NULL,
  `PedId` int NOT NULL,
  PRIMARY KEY (`PagId`),
  KEY `FK_Pagamento_1` (`PedId`),
  CONSTRAINT `FK_Pagamento_1` FOREIGN KEY (`PedId`) REFERENCES `pedido` (`PedId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela imperium.pagamento: ~0 rows (aproximadamente)
DELETE FROM `pagamento`;

-- Copiando estrutura para tabela imperium.pedido
DROP TABLE IF EXISTS `pedido`;
CREATE TABLE IF NOT EXISTS `pedido` (
  `PedId` int NOT NULL AUTO_INCREMENT,
  `PedData` datetime NOT NULL,
  `PedValorTotal` decimal(10,2) NOT NULL,
  `PedFormEnt` smallint NOT NULL,
  `PedStatus` smallint NOT NULL,
  `PedFormPag` smallint NOT NULL,
  `UsuId` int NOT NULL,
  `EndEntId` int NOT NULL,
  PRIMARY KEY (`PedId`),
  KEY `UsuId` (`UsuId`),
  KEY `EndEntId` (`EndEntId`),
  CONSTRAINT `pedido_ibfk_1` FOREIGN KEY (`UsuId`) REFERENCES `usuario` (`UsuId`),
  CONSTRAINT `pedido_ibfk_2` FOREIGN KEY (`EndEntId`) REFERENCES `enderecoentrega` (`EndEntId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela imperium.pedido: ~0 rows (aproximadamente)
DELETE FROM `pedido`;

-- Copiando estrutura para tabela imperium.pedidoproduto
DROP TABLE IF EXISTS `pedidoproduto`;
CREATE TABLE IF NOT EXISTS `pedidoproduto` (
  `PedProId` int NOT NULL AUTO_INCREMENT,
  `PedProQtd` int NOT NULL,
  `PedProPrecoUnitario` decimal(10,2) NOT NULL,
  `PedId` int NOT NULL,
  `RoupaId` int NOT NULL,
  PRIMARY KEY (`PedProId`),
  KEY `PedId` (`PedId`),
  KEY `RoupaId` (`RoupaId`),
  CONSTRAINT `pedidoproduto_ibfk_1` FOREIGN KEY (`PedId`) REFERENCES `pedido` (`PedId`),
  CONSTRAINT `pedidoproduto_ibfk_2` FOREIGN KEY (`RoupaId`) REFERENCES `roupa` (`RoupaId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela imperium.pedidoproduto: ~0 rows (aproximadamente)
DELETE FROM `pedidoproduto`;

-- Copiando estrutura para tabela imperium.roupa
DROP TABLE IF EXISTS `roupa`;
CREATE TABLE IF NOT EXISTS `roupa` (
  `RoupaId` int NOT NULL AUTO_INCREMENT,
  `RoupaNome` varchar(100) NOT NULL,
  `RoupaModelUrl` varchar(255) NOT NULL,
  `RoupaImgUrl` varchar(255) NOT NULL,
  `RoupaValor` decimal(10,2) NOT NULL,
  `CatRId` int NOT NULL,
  PRIMARY KEY (`RoupaId`),
  UNIQUE KEY `RoupaImgUrl` (`RoupaImgUrl`),
  UNIQUE KEY `idx_roupa_nome_modelo` (`RoupaNome`,`RoupaModelUrl`),
  KEY `FK_Roupa_4` (`CatRId`),
  CONSTRAINT `FK_Roupa_4` FOREIGN KEY (`CatRId`) REFERENCES `catroupa` (`CatRId`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela imperium.roupa: ~63 rows (aproximadamente)
DELETE FROM `roupa`;
INSERT INTO `roupa` (`RoupaId`, `RoupaNome`, `RoupaModelUrl`, `RoupaImgUrl`, `RoupaValor`, `CatRId`) VALUES
	(1, 'Tênis Nike Air Jordan', '/models/calcados/nike_air_jordan/scene.gltf', 'img/tenisjordan.png', 449.90, 1),
	(2, 'Nike Phantom', '/models/calcados/nike_fotball_shoe/scene.gltf', 'img/phantom.png', 329.90, 1),
	(3, 'Adidas Ozelia', '/models/calcados/adidas_ozelia/scene.gltf', 'img/ozelia.png', 299.90, 1),
	(4, 'Nike SB', '/models/calcados/nike_sb_charge_cnvs/scene.gltf', 'img/nikesb.png', 359.90, 1),
	(5, 'Yeezy', '/models/calcados/glow_green_yeezy_slides/scene.gltf', 'img/yeezy.png', 489.90, 1),
	(6, 'AIR JORDAN 4 LIGHTNING GS', '/models/calcados/air_jordan_4_lightning_gs/scene.gltf', 'img/air_jordan_4_lightning_gs.png', 539.90, 1),
	(7, 'AIR JORDAN 7 PATTA', '/models/calcados/air_jordan_7_patta/scene.gltf', 'img/air_jordan_7_patta.png', 429.90, 1),
	(8, 'Balenciaga Track Black', '/models/calcados/balenciaga_track_black/scene.gltf', 'img/balenciaga_track_black.png', 599.90, 1),
	(9, 'Balenciaga Track White', '/models/calcados/balenciaga_track_white/scene.gltf', 'img/balenciaga_track_white.png', 589.90, 1),
	(10, 'Balenciaga Triple S', '/models/calcados/balenciaga_triple_s_beige_green_yellow_2018/scene.gltf', 'img/balenciaga_triple_s_beige_green_yellow_2018.png', 629.90, 1),
	(11, 'NIKE AIR FORCE 1 LOW TRAVIS SCOTT S', '/models/calcados/nike_air_force_1_low_travis_scott_cactus_jack/scene.gltf', 'img/nike_air_force_1_low_travis_scott_cactus_jack.png', 459.90, 1),
	(12, 'NIKE AIR FORCE 1 LOW WHITE', '/models/calcados/nike_air_force_1_low_white/scene.gltf', 'img/nike_air_force_1_low_white.png', 299.90, 1),
	(13, 'NIKE AIR FORCE 1 MID WHITE', '/models/calcados/nike_air_force_1_mid_white/scene.gltf', 'img/nike_air_force_1_mid_white.png', 329.90, 1),
	(14, 'NIKE DUNK LOW OFF WHITE LOT 1', '/models/calcados/nike_dunk_low_off_white_lot_1/scene.gltf', 'img/nike_dunk_low_off_white_lot_1.png', 479.90, 1),
	(15, 'NIKE DUNK OFF WHITE LOT 33', '/models/calcados/nike_dunk_off_white_lot_33/scene.gltf', 'img/nike_dunk_off_white_lot_33.png', 449.90, 1),
	(16, 'Tiempo Legend', '/models/calcados/2014_-_tiempo_legend_-_8238169/scene.gltf', 'img/tiempo.png', 349.90, 1),
	(17, 'Nike Dunk', '/models/calcados/nike_dunk/scene.gltf', 'img/dunk.png', 389.90, 1),
	(18, 'Jordan 4 Retro SB Pine Green', '/models/calcados/jordan_4_retro_sb_pine_green/scene.gltf', 'img/jordan4.png', 559.90, 1),
	(19, 'Nike air 720', '/models/calcados/nike_air_720/scene.gltf', 'img/air720.png', 319.90, 1),
	(20, 'Baggy Pants', '/models/streetwears/baggy_pants_free/scene.gltf', 'img/baggy_pants.png', 139.90, 2),
	(21, 'Black Pants', '/models/streetwears/black_white_pants_model/scene.gltf', 'img/blackpants.png', 149.90, 2),
	(22, 'Oversize Baggy', '/models/streetwears/oversized_baggy_custom_jeans/scene.gltf', 'img/oversizebaggy.png', 129.90, 2),
	(23, 'Rave Pants', '/models/streetwears/rave_pants__phat_pants/scene.gltf', 'img/ravepants.png', 139.90, 2),
	(24, 'Skinny Pants', '/models/streetwears/skinny_pants_free/scene.gltf', 'img/skinnypants.png', 149.90, 2),
	(25, 'Jeans Feminino', '/models/streetwears/jeansfem/scene.gltf', 'img/jeansfem.png', 99.90, 2),
	(26, 'Jeans Masculino', '/models/streetwears/jeansmasc/scene.gltf', 'img/jeansmasc.png', 109.90, 2),
	(27, 'Jnco Twin Baggy', '/models/streetwears/jnco_twin_cannon_baggy_jeans/scene.gltf', 'img/jncotwin_baggy.png', 159.90, 2),
	(28, 'Sweet Pants', '/models/streetwears/my_sweet_piano_pants_with_bones/scene.gltf', 'img/sweetpants.png', 129.90, 2),
	(29, 'Corrupted Pants', '/models/streetwears/corrupted_hoodie_and_pants/scene.gltf', 'img/corruptedpants.png', 119.90, 5),
	(30, 'Roadies Pants', '/models/streetwears/roadies_hoodie_and_pants/scene.gltf', 'img/roadiespants.png', 119.90, 5),
	(31, 'Conjunto Amarelo', '/models/conjunto_3/scene.gltf', 'img/conjuntoamarelo.png', 189.90, 5),
	(32, 'Conjunto Azul', '/models/conjunto_2/scene.gltf', 'img/conjuntoazul.png', 189.90, 5),
	(33, 'Conjunto Branco', '/models/conjunto_6/scene.gltf', 'img/conjuntobranco.png', 179.90, 5),
	(34, 'Conjunto Dark', '/models/conjunto_1/scene.gltf', 'img/conjuntodark.png', 199.90, 5),
	(35, 'Conjunto Preto', '/models/conjunto_5/scene.gltf', 'img/conjuntopreto.png', 189.90, 5),
	(36, 'Conjunto Verde', '/models/conjunto_8/scene.gltf', 'img/conjuntoverde.png', 199.90, 5),
	(37, 'Conjunto Vermelho', '/models/conjunto_4/scene.gltf', 'img/conjuntovermelho.png', 199.90, 5),
	(38, 'Conjunto White', '/models/conjunto_7/scene.gltf', 'img/conjuntowhite.png', 189.90, 5),
	(39, 'Iridescent Coat', '/models/streetwears/iridescent_coat/scene.gltf', 'img/iridescent_coat.png', 199.90, 3),
	(40, 'Leather Jacket', '/models/streetwears/leather_jacket/scene.gltf', 'img/leather_jacket.png', 229.90, 3),
	(41, 'Oversize Sweater', '/models/streetwears/oversized_sweater/scene.gltf', 'img/oversizesweater.png', 159.90, 3),
	(42, 'Sweet Hoodie', '/models/streetwears/my_sweet_piano_hoodie_with_bones/scene.gltf', 'img/sweethoodie.png', 189.90, 3),
	(43, 'Classic Black Flame Hoodie', '/models/streetwears/classic_black_flame_hoodie/scene.gltf', 'img/classic_black_flame_hoodie.png', 189.90, 3),
	(44, 'Green Shirt Hood Scan Medpoly', '/models/streetwears/green_shirt_hood_scan_medpoly/scene.gltf', 'img/green_shirt_hood_scan_medpoly.png', 159.90, 3),
	(45, 'Oversized Hoodie', '/models/streetwears/oversized_hoodie/scene.gltf', 'img/oversized_hoodie.png', 189.90, 3),
	(46, 'Tshirt', '/models/streetwears/oversized_t-shirt/scene.gltf', 'img/tshirt.png', 79.90, 4),
	(47, 'T-shirt Amazigh Traditional', '/models/streetwears/amazigh_traditional_t-shirt/scene.gltf', 'img/amazigh_traditional_t-shirt.png', 89.90, 4),
	(48, 'T-shirt Red', '/models/streetwears/red_t-shirt/scene.gltf', 'img/red_t-shirt.png', 79.90, 4),
	(49, 'T-shirt Amazigh', '/models/streetwears/amazigh_t-shirt/scene.gltf', 'img/amazigh_t-shirt.png', 89.90, 4),
	(50, 'Shirt Black', '/models/streetwears/shirt/scene.gltf', 'img/shirt.png', 109.90, 4),
	(51, 'Shirt The Punisher', '/models/streetwears/shirt_the_punisher/scene.gltf', 'img/shirt_the_punisher.png', 99.90, 4),
	(52, 'Cool Shirt Scan Medpoly', '/models/streetwears/cool_shirt_scan_medpoly/scene.gltf', 'img/cool_shirt_scan_medpoly.png', 129.90, 4),
	(53, 'Teemu Selannes Hockey Shirt', '/models/streetwears/teemu_selannes_hockey_shirt/scene.gltf', 'img/teemu_selannes_hockey_shirt.png', 149.90, 4),
	(54, 'FC Porto Shirt', '/models/streetwears/fc_porto_concept_shirt/scene.gltf', 'img/fc_porto_concept_shirt.png', 169.90, 4),
	(55, 'White T-shirt With Print', '/models/streetwears/white_t-shirt_with_print/scene.gltf', 'img/white_t-shirt_with_print.png', 89.90, 4),
	(56, 'Mens Caro Flannel Shirt', '/models/streetwears/mens_caro_flannel_shirt/scene.gltf', 'img/mens_caro_flannel_shirt.png', 139.90, 4),
	(57, 'Paris Moon Upside Down T-shirt', '/models/streetwears/paris_moon_upside_down_t-shirt/scene.gltf', 'img/paris_moon_upside_down_t-shirt.png', 109.90, 4),
	(58, 'Monalisa T-shirt', '/models/streetwears/off-white_monalisa_black_t-shirt/scene.gltf', 'img/off-white_monalisa_black_t-shirt.png', 129.90, 4),
	(59, 'Cap', '/models/streetwears/cap/scene.gltf', 'img/cap.png', 49.90, 6),
	(60, 'Baseball Cap NY', '/models/streetwears/baseball_cap_ny/scene.gltf', 'img/baseball_cap_ny.png', 59.90, 6),
	(61, 'Samurai Cap', '/models/streetwears/samurai_cap_3d_model/scene.gltf', 'img/samurai_cap_3d_model.png', 69.90, 6),
	(62, 'Red Ice Cap', '/models/streetwears/red_ice_cap/scene.gltf', 'img/red_ice_cap.png', 59.90, 6),
	(63, 'Gucci Hat Model White', '/models/streetwears/gucci_hat_model_white/scene.gltf', 'img/gucci_hat_model_white.png', 79.90, 6);

-- Copiando estrutura para tabela imperium.usuario
DROP TABLE IF EXISTS `usuario`;
CREATE TABLE IF NOT EXISTS `usuario` (
  `UsuId` int NOT NULL AUTO_INCREMENT,
  `UsuUID` varchar(255) NOT NULL,
  `UsuEmail` varchar(150) NOT NULL,
  `UsuNome` varchar(255) NOT NULL,
  `UsuCpf` varchar(14) DEFAULT NULL,
  `UsuTel` varchar(15) DEFAULT NULL,
  `UsuDataNasc` date DEFAULT NULL,
  `UsuFuncao` enum('CLIENTE','FUNCIONARIO','GERENTE') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'CLIENTE',
  PRIMARY KEY (`UsuId`),
  UNIQUE KEY `UsuUID` (`UsuUID`),
  UNIQUE KEY `UsuEmail` (`UsuEmail`),
  UNIQUE KEY `UsuCpf` (`UsuCpf`),
  UNIQUE KEY `UsuTel` (`UsuTel`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Copiando dados para a tabela imperium.usuario: ~0 rows (aproximadamente)
DELETE FROM `usuario`;
INSERT INTO `usuario` (`UsuId`, `UsuUID`, `UsuEmail`, `UsuNome`, `UsuCpf`, `UsuTel`, `UsuDataNasc`, `UsuFuncao`) VALUES
	(1, 'lRE2GD8ZkBet0AIZyXw1sYNQeUH2', 'fe.akio20@gmail.com', 'Fernando Akio Carreiro', '45316952839', '11930234270', '2008-05-20', 'CLIENTE'),
	(2, '3N9EheeXVDPY2sMRtm6SkNFutS63', 'natgretakaleo@gmail.com', 'Natália Medeiros Ando', '00000000000', NULL, NULL, 'CLIENTE');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
