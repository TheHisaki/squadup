USE squadup;

ALTER TABLE users 
ADD COLUMN availability_morning TINYINT(1) DEFAULT 0,
ADD COLUMN availability_afternoon TINYINT(1) DEFAULT 0,
ADD COLUMN availability_evening TINYINT(1) DEFAULT 0,
ADD COLUMN availability_night TINYINT(1) DEFAULT 0; 