package backend

import (
	"database/sql"
	_ "modernc.org/sqlite"
)

type AppDB struct {
	db *sql.DB
}

func InitDB() (*AppDB, error) {
	db, err := sql.Open("sqlite", "./database.db")
	if err != nil {
		return nil, err
	}

	query := `
	CREATE TABLE IF NOT EXISTS customers (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		name TEXT,
		phone TEXT
	);
	CREATE TABLE IF NOT EXISTS inventory (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		item_name TEXT,
		stock INTEGER
	);`
	_, err = db.Exec(query)
	return &AppDB{db: db}, err
}
