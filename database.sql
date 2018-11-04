/* Users table */
CREATE TABLE users (
    id tinyint(3) unsigned auto_increment,
    email varchar(255),
    password char(128),
    name varchar(64),
    access_level tinyint(1) unsigned,
    token char(64),
    session char(64),
    PRIMARY KEY (id)
);

/* Projects table */
CREATE TABLE projects (
    id tinyint(3) unsigned auto_increment,
    user_id tinyint(3) unsigned,
    title varchar(255),
    description varchar(511),
    glossary text(4095),
    added timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

/* Source segments table */
CREATE TABLE source_segments (
    id smallint(5) unsigned auto_increment,
    project_id tinyint(3) unsigned,
    text text(50000),
    PRIMARY KEY (id),
    FOREIGN KEY (project_id) REFERENCES projects(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

/* Target segments table */
CREATE TABLE target_segments (
    id smallint(5) unsigned,
    project_id tinyint(3) unsigned,
    user_id tinyint(3) unsigned,
    text text(50000),
    complete tinyint(1) NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (project_id) REFERENCES projects(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

/* How would you translate x? queries*/
CREATE TABLE requests (
	id mediumint(8) unsigned auto_increment,
	user_id tinyint(3) unsigned,
	/*project_id tinyint(3) unsigned,*/
	segment_id smallint(5) unsigned,
	context text(4095),
	text varchar(1023),
	open tinyint(1) unsigned NOT NULL,
	added timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	FOREIGN KEY (segment_id) REFERENCES source_segments(id)
	ON DELETE CASCADE
	ON UPDATE CASCADE
);

/* Answers table */
CREATE TABLE answers (
	id int(10) unsigned auto_increment,
	user_id tinyint(3) unsigned,
	/*project_id tinyint(3) unsigned,
	segment_id smallint(5) unsigned,*/
	request_id mediumint(8) unsigned,
	text varchar(1023),
	added timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	FOREIGN KEY (request_id) REFERENCES requests(id)
	ON DELETE CASCADE
	ON UPDATE CASCADE
);