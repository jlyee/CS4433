CREATE TABLE image(
	image_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
	image_source_id INT UNSIGNED NOT NULL,
	image_name VARCHAR(255) NOT NULL,
	CONSTRAINT FOREIGN KEY(image_source_id) REFERENCES image_source(image_source_id) ON DELETE CASCADE,
	CONSTRAINT UNIQUE (image_name));