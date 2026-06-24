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
