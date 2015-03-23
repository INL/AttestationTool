LOCK TABLES `types` WRITE;
INSERT INTO `types`(id, name, color, shortcut) 
VALUES (1,'person','#FDD017', null),(2,'organisation','#FE9F52', null),(3,'location','#A1BFF7', null),(4,'misc','#C8BBBE', null);
UNLOCK TABLES;
