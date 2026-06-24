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
