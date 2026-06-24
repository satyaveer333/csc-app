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
