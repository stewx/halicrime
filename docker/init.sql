CREATE TABLE IF NOT EXISTS `events` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `latitude` FLOAT NOT NULL,
    `longitude` FLOAT NOT NULL,
    `street_name` VARCHAR(255),
    `date` DATE NOT NULL,
    `event_id` VARCHAR(255) NOT NULL,
    `event_type` VARCHAR(255) NOT NULL,
    `event_type_id` VARCHAR(255) NOT NULL,
    `date_added` DATE NOT NULL,
    PRIMARY KEY ( `id` )
);

CREATE TABLE IF NOT EXISTS `subscriptions` (
    `guid` VARCHAR(255) NOT NULL,
    `created` TIMESTAMP NOT NULL,
    `latitude` FLOAT NOT NULL, 
    `longitude` FLOAT NOT NULL, 
    `radius` FLOAT NOT NULL, 
    `name` VARCHAR(255),
    `email` VARCHAR(255) NOT NULL,
    `activated` INT,
    PRIMARY KEY ( `guid` ),
    UNIQUE ( `email` )
);