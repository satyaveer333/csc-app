import { useState } from 'react';
import './App.css';
import {
    ConvertToPDF,
    ScanToFolder,
    PrintFile,
    OptimizePDF,
    OptimizeImage
} from "../wailsjs/go/main/App";

type TabType = 'docx' | 'scan' | 'print' | 'pdf' | 'image';
type StatusType = {
    type: 'success' | 'error' | 'info';
    text: string;
} | null;

function App() {
    const [activeTab, setActiveTab] = useState<TabType>('docx');
    const [isLoading, setIsLoading] = useState(false);
    const [status, setStatus] = useState<StatusType>(null);

    // Form inputs state
    const [docxPath, setDocxPath] = useState('');
    const [docxOutputDir, setDocxOutputDir] = useState('');
    const [scanSavePath, setScanSavePath] = useState('C:\\Users\\Public\\Pictures\\scanned.jpg');
    const [printFilePath, setPrintFilePath] = useState('');
    const [pdfInputPath, setPdfInputPath] = useState('');
    const [pdfOutputPath, setPdfOutputPath] = useState('');
    const [imageInputPath, setImageInputPath] = useState('');
    const [imageOutputPath, setImageOutputPath] = useState('');
    const [imageQuality, setImageQuality] = useState(80);

    const showStatus = (type: 'success' | 'error' | 'info', text: string) => {
        setStatus({ type, text });
    };

    const handleAction = async (actionFn: () => Promise<any>, successMsg: string) => {
        setIsLoading(true);
        setStatus(null);
        try {
            await actionFn();
            showStatus('success', successMsg);
        } catch (error: any) {
            console.error(error);
            showStatus('error', error?.message || error || 'An unexpected error occurred in backend.');
        } finally {
            setIsLoading(false);
        }
    };

    // Handlers
    const handleDocxConvert = () => {
        if (!docxPath || !docxOutputDir) {
            showStatus('error', 'Please fill in both the DOCX file path and the output directory.');
            return;
        }
        handleAction(
            () => ConvertToPDF(docxPath, docxOutputDir),
            'Document converted to PDF successfully!'
        );
    };

    const handleScan = () => {
        if (!scanSavePath) {
            showStatus('error', 'Please enter a save path for the scanned image.');
            return;
        }
        handleAction(
            () => ScanToFolder(scanSavePath),
            `Document scanned and saved to ${scanSavePath} successfully!`
        );
    };

    const handlePrint = () => {
        if (!printFilePath) {
            showStatus('error', 'Please enter the path of the file to print.');
            return;
        }
        handleAction(
            () => PrintFile(printFilePath),
            'Print command sent to default printer successfully!'
        );
    };

    const handlePdfOptimize = () => {
        if (!pdfInputPath || !pdfOutputPath) {
            showStatus('error', 'Please enter both the input and output PDF paths.');
            return;
        }
        handleAction(
            () => OptimizePDF(pdfInputPath, pdfOutputPath),
            'PDF optimized successfully!'
        );
    };

    const handleImageOptimize = () => {
        if (!imageInputPath || !imageOutputPath) {
            showStatus('error', 'Please enter both the input and output image paths.');
            return;
        }
        handleAction(
            () => OptimizeImage(imageInputPath, imageOutputPath, imageQuality),
            'Image optimized and compressed successfully!'
        );
    };

    return (
        <div className="dashboard-container">
            <header className="dashboard-header">
                <h1 className="dashboard-title">CSC Manager</h1>
                <p className="dashboard-subtitle">Local Utilities Dashboard & Hardware Integrations</p>
            </header>

            {/* Navigation Tabs */}
            <div className="tabs-container">
                <button 
                    className={`tab-button ${activeTab === 'docx' ? 'active' : ''}`}
                    onClick={() => { setActiveTab('docx'); setStatus(null); }}
                >
                    <svg className="icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor"/>
                        <polyline points="14 2 14 8 20 8" stroke="currentColor"/>
                        <line x1="16" y1="13" x2="8" y2="13" stroke="currentColor"/>
                        <line x1="16" y1="17" x2="8" y2="17" stroke="currentColor"/>
                        <polyline points="10 9 9 9 8 9" stroke="currentColor"/>
                    </svg>
                    Word to PDF
                </button>

                <button 
                    className={`tab-button ${activeTab === 'scan' ? 'active' : ''}`}
                    onClick={() => { setActiveTab('scan'); setStatus(null); }}
                >
                    <svg className="icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="10" width="18" height="11" rx="2" ry="2" stroke="currentColor"/>
                        <path d="M7 10V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v5" stroke="currentColor"/>
                        <line x1="12" y1="14" x2="12" y2="17" stroke="currentColor"/>
                    </svg>
                    Scan Document
                </button>

                <button 
                    className={`tab-button ${activeTab === 'print' ? 'active' : ''}`}
                    onClick={() => { setActiveTab('print'); setStatus(null); }}
                >
                    <svg className="icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <polyline points="6 9 6 2 18 2 18 9" stroke="currentColor"/>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke="currentColor"/>
                        <rect x="6" y="14" width="12" height="8" stroke="currentColor"/>
                    </svg>
                    Print File
                </button>

                <button 
                    className={`tab-button ${activeTab === 'pdf' ? 'active' : ''}`}
                    onClick={() => { setActiveTab('pdf'); setStatus(null); }}
                >
                    <svg className="icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor"/>
                        <polyline points="14 2 14 8 20 8" stroke="currentColor"/>
                        <path d="M9 15h3a1.5 1.5 0 0 0 0-3H9v6" stroke="currentColor"/>
                    </svg>
                    Optimize PDF
                </button>

                <button 
                    className={`tab-button ${activeTab === 'image' ? 'active' : ''}`}
                    onClick={() => { setActiveTab('image'); setStatus(null); }}
                >
                    <svg className="icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke="currentColor"/>
                        <circle cx="8.5" cy="8.5" r="1.5" stroke="currentColor"/>
                        <polyline points="21 15 16 10 5 21" stroke="currentColor"/>
                    </svg>
                    Optimize Image
                </button>
            </div>

            {/* Dashboard Content Panel */}
            <main className="card-content">
                {/* 1. DOCX to PDF */}
                {activeTab === 'docx' && (
                    <div>
                        <div className="card-header">
                            <h2 className="card-title">Word Document to PDF</h2>
                            <p className="card-description">Convert a local DOCX file to PDF format using LibreOffice integration.</p>
                        </div>
                        <div className="form-group">
                            <label className="form-label">DOCX File Absolute Path</label>
                            <input 
                                type="text" 
                                className="input-field" 
                                placeholder="C:\Users\Admin\Documents\invoice.docx"
                                value={docxPath}
                                onChange={(e) => setDocxPath(e.target.value)}
                            />
                            <span className="helper-text">Enter the complete local path to the source word document.</span>
                        </div>
                        <div className="form-group">
                            <label className="form-label">Output Directory Path</label>
                            <input 
                                type="text" 
                                className="input-field" 
                                placeholder="C:\Users\Admin\Documents\Converted"
                                value={docxOutputDir}
                                onChange={(e) => setDocxOutputDir(e.target.value)}
                            />
                            <span className="helper-text">Target folder where the converted PDF will be saved.</span>
                        </div>
                        <button className="action-button" onClick={handleDocxConvert} disabled={isLoading}>
                            {isLoading ? <div className="spinner"></div> : 'Convert to PDF'}
                        </button>
                    </div>
                )}

                {/* 2. Scan Document */}
                {activeTab === 'scan' && (
                    <div>
                        <div className="card-header">
                            <h2 className="card-title">Scan from Scanner</h2>
                            <p className="card-description">Trigger your default connected WIA scanner and save the output locally.</p>
                        </div>
                        <div className="form-group">
                            <label className="form-label">Destination File Save Path</label>
                            <input 
                                type="text" 
                                className="input-field" 
                                placeholder="C:\Users\Public\Pictures\scanned.jpg"
                                value={scanSavePath}
                                onChange={(e) => setScanSavePath(e.target.value)}
                            />
                            <span className="helper-text">Absolute path including file name and extension (e.g. .jpg) where scanned file will be written.</span>
                        </div>
                        <button className="action-button" onClick={handleScan} disabled={isLoading}>
                            {isLoading ? <div className="spinner"></div> : 'Start Scanning'}
                        </button>
                    </div>
                )}

                {/* 3. Print File */}
                {activeTab === 'print' && (
                    <div>
                        <div className="card-header">
                            <h2 className="card-title">Send to Printer</h2>
                            <p className="card-description">Send a document directly to the default active Windows printer.</p>
                        </div>
                        <div className="form-group">
                            <label className="form-label">Document File Path</label>
                            <input 
                                type="text" 
                                className="input-field" 
                                placeholder="C:\Users\Admin\Documents\report.pdf"
                                value={printFilePath}
                                onChange={(e) => setPrintFilePath(e.target.value)}
                            />
                            <span className="helper-text">Support depends on the printer's native capability (PDF/TXT are generally supported).</span>
                        </div>
                        <button className="action-button" onClick={handlePrint} disabled={isLoading}>
                            {isLoading ? <div className="spinner"></div> : 'Print Document'}
                        </button>
                    </div>
                )}

                {/* 4. Optimize PDF */}
                {activeTab === 'pdf' && (
                    <div>
                        <div className="card-header">
                            <h2 className="card-title">Optimize & Compress PDF</h2>
                            <p className="card-description">Optimize file layout and compress size of target PDF document using pdfcpu.</p>
                        </div>
                        <div className="form-group">
                            <label className="form-label">Input PDF File Path</label>
                            <input 
                                type="text" 
                                className="input-field" 
                                placeholder="C:\Users\Admin\Documents\unoptimized.pdf"
                                value={pdfInputPath}
                                onChange={(e) => setPdfInputPath(e.target.value)}
                            />
                        </div>
                        <div className="form-group">
                            <label className="form-label">Optimized Output PDF Save Path</label>
                            <input 
                                type="text" 
                                className="input-field" 
                                placeholder="C:\Users\Admin\Documents\optimized.pdf"
                                value={pdfOutputPath}
                                onChange={(e) => setPdfOutputPath(e.target.value)}
                            />
                        </div>
                        <button className="action-button" onClick={handlePdfOptimize} disabled={isLoading}>
                            {isLoading ? <div className="spinner"></div> : 'Optimize PDF'}
                        </button>
                    </div>
                )}

                {/* 5. Optimize Image */}
                {activeTab === 'image' && (
                    <div>
                        <div className="card-header">
                            <h2 className="card-title">Optimize & Compress Image</h2>
                            <p className="card-description">Compress JPEG/PNG image files by tuning the quality compression level.</p>
                        </div>
                        <div className="form-group">
                            <label className="form-label">Input Image File Path</label>
                            <input 
                                type="text" 
                                className="input-field" 
                                placeholder="C:\Users\Admin\Pictures\photo.jpg"
                                value={imageInputPath}
                                onChange={(e) => setImageInputPath(e.target.value)}
                            />
                        </div>
                        <div className="form-group">
                            <label className="form-label">Compressed Output Image Save Path</label>
                            <input 
                                type="text" 
                                className="input-field" 
                                placeholder="C:\Users\Admin\Pictures\photo_compressed.jpg"
                                value={imageOutputPath}
                                onChange={(e) => setImageOutputPath(e.target.value)}
                            />
                        </div>
                        <div className="form-group">
                            <div className="slider-group">
                                <div className="slider-header">
                                    <label className="form-label" style={{ marginBottom: 0 }}>Compression Quality</label>
                                    <span className="slider-val">{imageQuality}%</span>
                                </div>
                                <input 
                                    type="range" 
                                    min="1" 
                                    max="100" 
                                    className="slider-input" 
                                    value={imageQuality}
                                    onChange={(e) => setImageQuality(Number(e.target.value))}
                                />
                            </div>
                            <span className="helper-text">Lower quality values yield smaller file sizes but reduced clarity. Recommended range: 60-85%.</span>
                        </div>
                        <button className="action-button" onClick={handleImageOptimize} disabled={isLoading}>
                            {isLoading ? <div className="spinner"></div> : 'Optimize Image'}
                        </button>
                    </div>
                )}

                {/* Status indicator */}
                {status && (
                    <div className={`status-panel ${status.type}`}>
                        {status.type === 'success' && (
                            <svg className="icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <polyline points="20 6 9 17 4 12" stroke="currentColor"/>
                            </svg>
                        )}
                        {status.type === 'error' && (
                            <svg className="icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="10" stroke="currentColor"/>
                                <line x1="15" y1="9" x2="9" y2="15" stroke="currentColor"/>
                                <line x1="9" y1="9" x2="15" y2="15" stroke="currentColor"/>
                            </svg>
                        )}
                        {status.type === 'info' && (
                            <svg className="icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="10" stroke="currentColor"/>
                                <line x1="12" y1="16" x2="12" y2="12" stroke="currentColor"/>
                                <line x1="12" y1="8" x2="12.01" y2="8" stroke="currentColor"/>
                            </svg>
                        )}
                        <span>{status.text}</span>
                    </div>
                )}
            </main>
        </div>
    );
}

export default App;
