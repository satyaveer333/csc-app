package main

import (
	"context"
	"csc-manager/backend"
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
