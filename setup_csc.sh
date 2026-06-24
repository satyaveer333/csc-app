#!/bin/bash

# Exit immediately if a command exits with a non-zero status
set -e

APP_NAME="csc-manager"
MODULE_NAME="csc-manager"

echo "🚀 Starting CSC Manager generation..."

# 1. Initialize the Wails project (React + TypeScript)
echo "📦 Initializing Wails project..."
wails init -n $APP_NAME -t react-ts
cd $APP_NAME

# 2. Create backend directory
mkdir -p backend

# 3. Generate backend/db.go
echo "📝 Writing Database module..."
cat << 'EOF' > backend/db.go
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
EOF

# 4. Generate backend/hardware.go
echo "📝 Writing Hardware module..."
cat << 'EOF' > backend/hardware.go
package backend

import (
	"os/exec"
	"github.com/alexbrainman/printer"
)

func PrintDocument(filePath string) error {
	name, err := printer.Default()
	if err != nil {
		return err
	}
	p, err := printer.Open(name)
	if err != nil {
		return err
	}
	defer p.Close()
	return nil
}

func ScanDocument(savePath string) error {
	script := `
		$deviceManager = New-Object -ComObject WIA.DeviceManager
		$device = $deviceManager.DeviceInfos.Item(1).Connect()
		$image = $device.Items[1].Transfer()
		$image.SaveFile("` + savePath + `")
	`
	cmd := exec.Command("powershell", "-Command", script)
	return cmd.Run()
}
EOF

# 5. Generate backend/libreoffice.go
echo "📝 Writing LibreOffice integration..."
cat << 'EOF' > backend/libreoffice.go
package backend

import (
	"os/exec"
	"path/filepath"
)

func ConvertDocxToPdf(docxPath string, outputDir string) error {
	sofficePath := filepath.Join(".", "LibreOfficePortable", "App", "libreoffice", "program", "soffice.exe")
	cmd := exec.Command(sofficePath, "--headless", "--convert-to", "pdf", "--outdir", outputDir, docxPath)
	return cmd.Run()
}
EOF

# 6. Generate backend/utilities.go
echo "📝 Writing Image/PDF Utilities..."
cat << 'EOF' > backend/utilities.go
package backend

import (
	"image"
	"image/jpeg"
	"os"
	"github.com/pdfcpu/pdfcpu/pkg/api"
)

func CompressPDF(inFile string, outFile string) error {
	return api.OptimizeFile(inFile, outFile, nil)
}

func CompressImage(inFile string, outFile string, quality int) error {
	file, err := os.Open(inFile)
	if err != nil {
		return err
	}
	defer file.Close()
	
	img, _, err := image.Decode(file)
	if err != nil {
		return err
	}

	out, err := os.Create(outFile)
	if err != nil {
		return err
	}
	defer out.Close()

	var opt jpeg.Options
	opt.Quality = quality
	return jpeg.Encode(out, img, &opt)
}
EOF

# 7. Overwrite app.go to bind the backend functions
echo "🔗 Binding Go backend to Wails frontend..."
cat << EOF > app.go
package main

import (
	"context"
	"${MODULE_NAME}/backend"
)

type App struct {
	ctx context.Context
	db  *backend.AppDB
}

func NewApp() *App {
	return &App{}
}

func (a *App) startup(ctx context.Context) {
	a.ctx = ctx
	db, _ := backend.InitDB()
	a.db = db
}

// Exposed functions for React Frontend
func (a *App) ConvertToPDF(path string, outputDir string) error {
	return backend.ConvertDocxToPdf(path, outputDir)
}

func (a *App) ScanToFolder(savePath string) error {
	return backend.ScanDocument(savePath)
}

func (a *App) PrintFile(filePath string) error {
	return backend.PrintDocument(filePath)
}

func (a *App) OptimizePDF(inFile string, outFile string) error {
	return backend.CompressPDF(inFile, outFile)
}

func (a *App) OptimizeImage(inFile string, outFile string, quality int) error {
	return backend.CompressImage(inFile, outFile, quality)
}
EOF

# 8. Fetch Dependencies
echo "📥 Downloading Go dependencies..."
go get modernc.org/sqlite
go get github.com/pdfcpu/pdfcpu/pkg/api
go get github.com/go-ole/go-ole
go get github.com/alexbrainman/printer
go mod tidy

# 9. Build the executable for Windows
echo "🔨 Compiling Windows Executable..."
wails build -platform windows/amd64

echo "✅ Build Complete!"
echo "Your executable is located at: $(pwd)/build/bin/csc-manager.exe"