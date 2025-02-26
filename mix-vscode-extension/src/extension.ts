import * as vscode from 'vscode';
import * as path from 'path';
import * as os from 'os';

const LANGUAGE_ID_MAP: { [key: string]: string } = {
    'tsx': 'typescriptreact',
    'jsx': 'javascriptreact',
    'js': 'javascript',
    'ts': 'typescript',
    'php': 'php',
    'py': 'python',
    'python': 'python',
    'css': 'css',
    'html': 'html',
    'vue': 'vue'
};

interface MixSection {
    language: string;
    content: string;
    startLine: number;
    endLine: number;
    startOffset: number;
    endOffset: number;
}

interface VirtualDocument {
    uri: vscode.Uri;
    version: number;
    document?: vscode.TextDocument;
    fsPath: string;  // Physical file path
}

const VIRTUAL_SCHEME = 'mix-virtual';

class MixDocumentProvider {
    public sections: Map<string, MixSection[]> = new Map();
    public virtualDocuments: Map<string, VirtualDocument> = new Map();
    private virtualContents: Map<string, string> = new Map();
    private diagnosticCollection: vscode.DiagnosticCollection;
    private disposables: vscode.Disposable[] = [];
    private diagnosticPollingInterval?: NodeJS.Timer;

    constructor() {
        this.diagnosticCollection = vscode.languages.createDiagnosticCollection('mix');
        // Register virtual document provider
        this.disposables.push(
            this.diagnosticCollection,
            vscode.workspace.registerTextDocumentContentProvider(VIRTUAL_SCHEME, {
                provideTextDocumentContent: (uri) => {
                    return this.virtualContents.get(uri.toString());
                }
            }),
            vscode.workspace.onDidChangeTextDocument(e => this.onDocumentChanged(e)),
            vscode.workspace.onDidCloseTextDocument(e => this.onDocumentClosed(e)),
            // Add diagnostic change handler
            vscode.languages.onDidChangeDiagnostics(e => this.onDiagnosticsChanged(e))
        );

        // Start diagnostic polling
        this.startDiagnosticPolling();
    }

    private getLanguageId(language: string): string {
        return LANGUAGE_ID_MAP[language] || language;
    }

    private parseMixDocument(document: vscode.TextDocument): MixSection[] {
        const text = document.getText();
        const sections: MixSection[] = [];
        const mixRegex = /<mix\s+lang="([^"]+)">([\s\S]*?)<\/mix>/g;
        
        let match;
        while ((match = mixRegex.exec(text)) !== null) {
            const language = this.getLanguageId(match[1]);
            const content = match[2];
            
            // Get the exact line numbers by counting newlines
            const beforeText = text.substring(0, match.index);
            const openingTag = text.substring(match.index, match.index + match[0].indexOf('>') + 1);
            
            const startLine = beforeText.split('\n').length - 1;
            const contentStartLine = startLine + openingTag.split('\n').length - 1;
            const contentLines = content.split('\n');
            const endLine = contentStartLine + contentLines.length - 1;

            // Trim trailing empty lines from content to prevent duplication
            let trimmedContent = content;
            const contentLinesTrimmed = [...contentLines];
            while (contentLinesTrimmed.length > 0 && contentLinesTrimmed[contentLinesTrimmed.length - 1].trim() === '') {
                contentLinesTrimmed.pop();
            }
            if (contentLinesTrimmed.length < contentLines.length) {
                trimmedContent = contentLinesTrimmed.join('\n');
            }

            sections.push({
                language,
                content: trimmedContent,
                startLine: contentStartLine,
                endLine,
                startOffset: match.index + match[0].indexOf('>') + 1,
                endOffset: match.index + match[0].length - '</mix>'.length
            });
        }
        
        return sections;
    }

    private async onDiagnosticsChanged(e: vscode.DiagnosticChangeEvent) {

        for (const uri of e.uris) {
            if (uri.scheme === VIRTUAL_SCHEME) {
                const originalUri = vscode.Uri.parse(uri.query);
                const diagnostics = vscode.languages.getDiagnostics(uri);
                
                const sections = this.sections.get(originalUri.toString());
                const section = sections?.find(s => 
                    this.getVirtualDocumentUri(originalUri, s.language).toString() === uri.toString()
                );

                if (section) {
                    const adjustedDiagnostics = diagnostics.map(diagnostic => {
                        const range = new vscode.Range(
                            this.getOriginalPosition(section, diagnostic.range.start),
                            this.getOriginalPosition(section, diagnostic.range.end)
                        );
                        const newDiagnostic = new vscode.Diagnostic(
                            range,
                            diagnostic.message,
                            diagnostic.severity
                        );
                        
                        // Copy over additional diagnostic properties
                        newDiagnostic.tags = diagnostic.tags;
                        newDiagnostic.code = diagnostic.code;
                        newDiagnostic.source = diagnostic.source;
                        newDiagnostic.relatedInformation = diagnostic.relatedInformation;

                        return newDiagnostic;
                    });

                    const existingDiagnostics = this.diagnosticCollection.get(originalUri) || [];
                    this.diagnosticCollection.set(originalUri, [...existingDiagnostics, ...adjustedDiagnostics]);
                }
            }
        }
    }
    public getVirtualDocumentUri(originalUri: vscode.Uri, language: string): vscode.Uri {
        const key = `${originalUri.toString()}-${language}`;
        if (!this.virtualDocuments.has(key)) {
            const originalPath = originalUri.fsPath;
            const dirPath = require('path').dirname(originalPath);
            const fileName = require('path').basename(originalPath);
            
            // Map typescriptreact to tsx for the file extension
            const fileExtension = language === 'typescriptreact' ? 'tsx' : language;
            
            // Create hidden file with .mix. prefix in same directory
            const hiddenFilePath = `${dirPath}/.mix.${fileName}.${fileExtension}`;
        
            const virtualUri = vscode.Uri.file(hiddenFilePath);
            
            this.virtualDocuments.set(key, { 
                uri: virtualUri,
                version: 0,
                fsPath: hiddenFilePath
            });

            // Add the file pattern to VS Code's files.exclude setting
            const config = vscode.workspace.getConfiguration();
            const filesExclude = config.get('files.exclude') as { [key: string]: boolean };
            if (!filesExclude['**/.mix.*']) {
                filesExclude['**/.mix.*'] = true;
                config.update('files.exclude', filesExclude, vscode.ConfigurationTarget.Workspace);
            }
        }
        return this.virtualDocuments.get(key)!.uri;
    }

    public async initialize(document: vscode.TextDocument) {
        // Add check for special URIs
        if (document.uri.scheme !== 'file' && document.uri.scheme !== 'untitled') {
            console.log(`Skipping non-file document: ${document.uri.toString()}`);
            return;
        }

        console.log(`Initializing document: ${document.uri.toString()}`);
        
        const oldSections = this.sections.get(document.uri.toString());
        const newSections = this.parseMixDocument(document);
        
        // Compare sections to see if we need to update
        if (this.areSectionsEqual(oldSections, newSections)) {
            console.log('Sections unchanged, skipping update');
            return;
        }

        this.diagnosticCollection.delete(document.uri);
        this.sections.set(document.uri.toString(), newSections);

        // Create or update virtual documents for each section
        for (const section of newSections) {
            console.log(`Creating/updating section for language: ${section.language}`);
            const virtualUri = this.getVirtualDocumentUri(document.uri, section.language);
            await this.createVirtualDocument(virtualUri, section.content, section.language, document);
        }

        // Clean up any old virtual documents that are no longer needed
        if (oldSections) {
            const newLanguages = new Set(newSections.map(s => s.language));
            const oldLanguages = new Set(oldSections.map(s => s.language));
            
            for (const lang of oldLanguages) {
                if (!newLanguages.has(lang)) {
                    const key = `${document.uri.toString()}-${lang}`;
                    const virtualDoc = this.virtualDocuments.get(key);
                    if (virtualDoc) {
                        await vscode.workspace.fs.delete(virtualDoc.uri);
                        this.virtualDocuments.delete(key);
                    }
                }
            }
        }
    }

    private areSectionsEqual(oldSections: MixSection[] | undefined, newSections: MixSection[]): boolean {
        if (!oldSections) return false;
        if (oldSections.length !== newSections.length) return false;

        return oldSections.every((oldSection, i) => {
            const newSection = newSections[i];
            return oldSection.language === newSection.language &&
                oldSection.content === newSection.content &&
                oldSection.startLine === newSection.startLine &&
                oldSection.endLine === newSection.endLine;
        });
    }


    private async createVirtualDocument(
        virtualUri: vscode.Uri,
        content: string,
        language: string,
        document: vscode.TextDocument
    ) {
        const key = virtualUri.toString();
        
        // Get the full document text
        const fullText = document.getText();
        
        // Create a whitespace version of the full text
        const whitespaceText = fullText.replace(/[^\n]/g, ' ');
        
        // Split into lines for manipulation
        const lines = whitespaceText.split('\n');
        
        // Get the section content lines
        const section = this.sections.get(document.uri.toString())?.find(s => 
            this.getVirtualDocumentUri(document.uri, s.language).toString() === virtualUri.toString()
        );
        
        if (!section) {
            throw new Error('Section not found');
        }

        // Insert the actual content at the correct position
        const contentLines = content.split('\n');
        for (let i = 0; i < contentLines.length; i++) {
            if (section.startLine + i < lines.length) {
                lines[section.startLine + i] = contentLines[i];
            } else {
                // If we're adding lines beyond the original document length, append them
                lines.push(contentLines[i]);
            }
        }

        // Add necessary imports for TSX/JSX files at the top, maintaining whitespace
        let finalContent = lines.join('\n'); 

        try {
            // Write content to the physical file
            await vscode.workspace.fs.writeFile(
                virtualUri,
                Buffer.from(finalContent, 'utf8')
            );

            // Open and force language services to recognize the file
            const doc = await vscode.workspace.openTextDocument(virtualUri);
            const mappedLanguage = this.getLanguageId(language);
            await vscode.languages.setTextDocumentLanguage(doc, mappedLanguage);

            // Configure TypeScript settings for this document
            if (language === 'typescriptreact' || language === 'typescript') {
                await vscode.workspace.getConfiguration('typescript', doc).update('suggest.autoImports', true);
                await vscode.workspace.getConfiguration('typescript', doc).update('suggest.enabled', true);
                await vscode.workspace.getConfiguration('javascript', doc).update('suggest.autoImports', true);
                await vscode.workspace.getConfiguration('javascript', doc).update('suggest.enabled', true);
            }

            const virtualDoc = this.virtualDocuments.get(key);
            if (virtualDoc) {
                virtualDoc.document = doc;
                virtualDoc.version++;
            }
            
            return doc;
        } catch (error) {
            console.error(`Failed to create virtual document: ${error}`);
            return null;
        }
    }

    private async onDocumentChanged(e: vscode.TextDocumentChangeEvent) {
        if (e.document.uri.scheme !== 'file' && e.document.uri.scheme !== 'untitled') {
            return;
        }

        if (e.document.languageId === 'mix') {
            const sections = this.sections.get(e.document.uri.toString());
            if (!sections) return;

            const newSections = this.parseMixDocument(e.document);
            this.sections.set(e.document.uri.toString(), newSections);

            // Track first occurrence of each language
            const seenLanguages = new Set<string>();
            
            // Update or create virtual documents for first occurrence of each language
            for (const section of newSections) {
                if (!seenLanguages.has(section.language)) {
                    const virtualUri = this.getVirtualDocumentUri(e.document.uri, section.language);
                    seenLanguages.add(section.language);
                    
                    try {
                        // Try to open the virtual document
                        let virtualDoc: vscode.TextDocument;
                        try {
                            virtualDoc = await vscode.workspace.openTextDocument(virtualUri);
                        } catch (error) {
                            // If the document doesn't exist, create it
                            console.log(`Creating new virtual document for ${section.language}`);
                            await this.createVirtualDocument(virtualUri, section.content, section.language, e.document);
                            continue;
                        }

                        // Create a whitespace version of the full document
                        const fullText = e.document.getText();
                        const lines = fullText.split('\n');
                        
                        // Replace all lines with whitespace except for the section's content
                        const processedLines = lines.map((line, i) => {
                            if (i < section.startLine || i > section.endLine) {
                                return ' '.repeat(line.length);
                            }
                            // For the section content, extract just the inner content without mix tags
                            if (i === section.startLine) {
                                const match = line.match(/<mix\s+lang="[^"]+">(.*)$/);
                                return match ? match[1] : line;
                            }
                            if (i === section.endLine) {
                                const match = line.match(/^(.*?)<\/mix>/);
                                return match ? match[1] : line;
                            }
                            return line;
                        });

                        const newContent = processedLines.join('\n');

                        // Replace entire content to keep document open and synchronized
                        const edit = new vscode.WorkspaceEdit();
                        edit.replace(
                            virtualUri,
                            new vscode.Range(
                                virtualDoc.positionAt(0),
                                virtualDoc.positionAt(virtualDoc.getText().length)
                            ),
                            newContent
                        );

                        await vscode.workspace.applyEdit(edit);

                        // Ensure language mode is set
                        if (virtualDoc.languageId !== section.language) {
                            await vscode.languages.setTextDocumentLanguage(virtualDoc, section.language);
                        }

                    } catch (error) {
                        console.error('Failed to update virtual document:', error);
                    }
                }
            }

            // Clean up removed sections
            const oldSections = sections;
            const newLanguages = new Set(newSections.map(s => s.language));
            for (const oldSection of oldSections) {
                if (!newLanguages.has(oldSection.language)) {
                    const key = `${e.document.uri.toString()}-${oldSection.language}`;
                    const virtualDoc = this.virtualDocuments.get(key);
                    if (virtualDoc) {
                        await vscode.workspace.fs.delete(virtualDoc.uri);
                        this.virtualDocuments.delete(key);
                    }
                }
            }
        }
    }

    private onDocumentClosed(document: vscode.TextDocument) {
        if (document.languageId === 'mix') {
            this.sections.delete(document.uri.toString());
            this.diagnosticCollection.delete(document.uri);
            
            // Clean up physical files
            const prefix = document.uri.toString();
            for (const [key, virtualDoc] of this.virtualDocuments.entries()) {
                if (key.startsWith(prefix)) {
                    vscode.workspace.fs.delete(virtualDoc.uri);
                    this.virtualDocuments.delete(key);
                }
            }
        }
    }

    public getSectionAtPosition(document: vscode.TextDocument, position: vscode.Position): MixSection | undefined {
        const sections = this.sections.get(document.uri.toString());
        if (!sections) return undefined;

        return sections.find(section => 
            position.line >= section.startLine && 
            position.line <= section.endLine
        );
    }

    public getOffsetPosition(section: MixSection, position: vscode.Position): vscode.Position {
        return position;  // No adjustment needed
    }

    public getOriginalPosition(section: MixSection, position: vscode.Position): vscode.Position {
        return position;  // No adjustment needed
    }

    public adjustLocationsToOriginal(
        section: MixSection,
        locations: vscode.Location[] | null | undefined
    ): vscode.Location[] | null {
        if (!locations) return null;
        
        return locations.map(location => {
            const range = new vscode.Range(
                this.getOriginalPosition(section, location.range.start),
                this.getOriginalPosition(section, location.range.end)
            );
            return new vscode.Location(location.uri, range);
        });
    }

    private startDiagnosticPolling() {
        this.diagnosticPollingInterval = setInterval(async () => {
            console.log('Starting diagnostic polling');
            const mixDocuments = vscode.workspace.textDocuments.filter(doc => 
                doc.languageId === 'mix'
            );

            for (const doc of mixDocuments) {
                const sections = this.sections.get(doc.uri.toString());
                if (!sections) continue;
                const seenLanguages: string[] = [];
                const duplicateLanguageSections: MixSection[] = [];
                const newDiagnostics: vscode.Diagnostic[] = [];
                for (const section of sections) {
                    if (seenLanguages.includes(section.language)) {
                        duplicateLanguageSections.push(section);
                    } else {
                        seenLanguages.push(section.language);
                    }
                    const virtualUri = this.getVirtualDocumentUri(doc.uri, section.language);
                    try {
                        const diagnostics = vscode.languages.getDiagnostics(virtualUri);
                        
                        const mappedDiagnostics = diagnostics.map(diag => {
                            const newDiagnostic = new vscode.Diagnostic(
                                new vscode.Range(
                                    this.getOriginalPosition(section, diag.range.start),
                                    this.getOriginalPosition(section, diag.range.end)
                                ),
                                diag.message,
                                diag.severity
                            );

                            // Copy over additional diagnostic properties
                            newDiagnostic.tags = diag.tags;
                            newDiagnostic.code = diag.code;
                            newDiagnostic.source = diag.source;
                            newDiagnostic.relatedInformation = diag.relatedInformation;

                            return newDiagnostic;
                        });

                        newDiagnostics.push(...mappedDiagnostics);
                    } catch (error) {
                        console.error(`Error getting diagnostics for ${virtualUri.toString()}:`, error);
                    }
                }

                if (duplicateLanguageSections.length > 0) {
                    duplicateLanguageSections.forEach(section => {
                        const diagnostic = new vscode.Diagnostic(
                            new vscode.Range(
                                doc.positionAt(section.startOffset),
                                doc.positionAt(section.endOffset)
                            ),
                            `Duplicate language sections: ${section.language}`,
                            vscode.DiagnosticSeverity.Error
                        );
                        newDiagnostics.push(diagnostic);
                    });
                }

                const currentDiagnostics = this.diagnosticCollection.get(doc.uri) || [];
                if (!this.areDiagnosticsEqual(currentDiagnostics, newDiagnostics)) {
                    this.diagnosticCollection.set(doc.uri, newDiagnostics);
                }
            }
        }, 2000);
    }

    private areDiagnosticsEqual(a: readonly vscode.Diagnostic[], b: readonly vscode.Diagnostic[]): boolean {
        if (a.length !== b.length) return false;

        return a.every((diagA, i) => {
            const diagB = b[i];
            return diagA.message === diagB.message &&
                diagA.severity === diagB.severity &&
                diagA.range.isEqual(diagB.range);
        });
    }

    public dispose() {
        this.disposables.forEach(d => d.dispose());
        this.virtualContents.clear();
        this.virtualDocuments.clear();
        this.sections.clear();
        this.diagnosticCollection.clear();
        if (this.diagnosticPollingInterval) {
            clearInterval(this.diagnosticPollingInterval);
        }
    }

    public async executeVirtualCommand(
        command: string,
        virtualUri: vscode.Uri,
        originalUri: vscode.Uri,
        section: MixSection,
        ...args: any[]
    ) {
        try {
            console.log('Executing virtual command:', command, virtualUri, originalUri, section, args);
            
            // Execute the command
            if (command === '_typescript.applyCompletionCommand') {
                const completionItem = args[0];
                await vscode.commands.executeCommand(command, completionItem);
            } else {
                await vscode.commands.executeCommand(command, virtualUri, ...args);
            }
            
            // Get the updated virtual document content
            const virtualDoc = await vscode.workspace.openTextDocument(virtualUri);
            const virtualContent = virtualDoc.getText();
            
            // Get the original document and content
            const originalDoc = await vscode.workspace.openTextDocument(originalUri);
            const originalText = originalDoc.getText();
            
            // Extract the section content from the original document
            const originalSectionContent = originalText.substring(section.startOffset, section.endOffset);
            
            // For TypeScript/JavaScript, we need to handle import additions specially
            if (section.language === 'typescriptreact' || section.language === 'javascriptreact' || 
                section.language === 'typescript' || section.language === 'javascript') {
                
                // Find the imports in the virtual document
                const virtualLines = virtualContent.split('\n');
                const importLines: string[] = [];
                
                for (const line of virtualLines) {
                    const trimmedLine = line.trim();
                    if (trimmedLine.startsWith('import ') && trimmedLine.includes('from ')) {
                        importLines.push(trimmedLine);
                    }
                }
                
                // Find the imports in the original content
                const originalLines = originalSectionContent.split('\n');
                const originalImportLines: string[] = [];
                let nonImportStartIndex = 0;
                
                // Preserve any leading empty lines
                let leadingEmptyLines = 0;
                while (leadingEmptyLines < originalLines.length && originalLines[leadingEmptyLines].trim() === '') {
                    leadingEmptyLines++;
                }
                
                for (let i = leadingEmptyLines; i < originalLines.length; i++) {
                    const trimmedLine = originalLines[i].trim();
                    if (trimmedLine.startsWith('import ') && trimmedLine.includes('from ')) {
                        originalImportLines.push(originalLines[i]);
                        nonImportStartIndex = i + 1;
                    } else if (trimmedLine !== '') {
                        break;
                    } else {
                        // Empty line, might be between imports
                        if (i > 0 && originalLines[i-1].trim().startsWith('import ')) {
                            nonImportStartIndex = i + 1;
                        }
                    }
                }
                
                // Find new imports that aren't in the original
                const newImports = importLines.filter(importLine => 
                    !originalImportLines.some(origImport => 
                        origImport.trim().includes(importLine.substring(importLine.indexOf('from ')))
                    )
                );
                
                // If there are new imports, add them to the original content
                if (newImports.length > 0) {
                    // Combine imports with the rest of the original content
                    const updatedContent = [
                        ...originalLines.slice(0, leadingEmptyLines), // Preserve leading empty lines
                        ...originalImportLines,
                        ...newImports,
                        '',  // Empty line after imports
                        ...originalLines.slice(nonImportStartIndex)
                    ].join('\n');
                    
                    // Create the edit
                    const edit = new vscode.WorkspaceEdit();
                    edit.replace(
                        originalUri,
                        new vscode.Range(
                            originalDoc.positionAt(section.startOffset),
                            originalDoc.positionAt(section.endOffset)
                        ),
                        updatedContent
                    );
                    await vscode.workspace.applyEdit(edit);
                }
            } else {
                // For other languages, just replace the content directly
                // Create the edit
                const edit = new vscode.WorkspaceEdit();
                edit.replace(
                    originalUri,
                    new vscode.Range(
                        originalDoc.positionAt(section.startOffset),
                        originalDoc.positionAt(section.endOffset)
                    ),
                    virtualContent
                );
                await vscode.workspace.applyEdit(edit);
            }

            // Re-sync the virtual document if needed
            await this.initialize(await vscode.workspace.openTextDocument(originalUri));
        } catch (error) {
            console.error(`Failed to execute virtual command ${command}:`, error);
            console.error('Command args:', args);
        }
    }
}

export function activate(context: vscode.ExtensionContext) {
    console.log('Activating Mix Language Support');
    const provider = new MixDocumentProvider();
    console.log('Provider initialized');

    // Initialize provider for already open documents
    vscode.workspace.textDocuments.forEach(doc => {
        // Add check for special URIs
        if ((doc.uri.scheme === 'file' || doc.uri.scheme === 'untitled') && doc.languageId === 'mix') {
            console.log(`Initializing existing document: ${doc.uri.toString()}`);
            provider.initialize(doc);
        }
    });

    // Watch for new documents being opened
    context.subscriptions.push(
        vscode.workspace.onDidOpenTextDocument(doc => {
            // Add check for special URIs
            if ((doc.uri.scheme === 'file' || doc.uri.scheme === 'untitled') && doc.languageId === 'mix') {
                console.log(`New document opened: ${doc.uri.toString()}`);
                provider.initialize(doc);
            }
        })
    );

    // Register providers for various language features
    const languageFeatures = [
        vscode.languages.registerCompletionItemProvider('mix', {
            async provideCompletionItems(document, position, token) {
                const section = provider.getSectionAtPosition(document, position);
                if (!section) return null;

                const virtualUri = provider.getVirtualDocumentUri(document.uri, section.language);
                const offsetPosition = provider.getOffsetPosition(section, position);

                try {
                    const completions = await vscode.commands.executeCommand<vscode.CompletionList>(
                        'vscode.executeCompletionItemProvider',
                        virtualUri,
                        offsetPosition
                    );

                    // Wrap any commands in our virtual command executor
                    completions?.items.forEach(item => {
                        if (item.command) {
                            const originalCommand = item.command;
                            item.command = {
                                command: 'mix.executeVirtualCommand',
                                title: originalCommand.title,
                                arguments: [
                                    originalCommand.command,
                                    virtualUri,
                                    document.uri,
                                    section,
                                    ...(originalCommand.arguments || [])
                                ]
                            };
                        }
                    });

                    return completions;
                } catch (error) {
                    console.error('Completion error:', error);
                    return null;
                }
            }
        }),

        vscode.languages.registerHoverProvider('mix', {
            async provideHover(document, position, token) {
                const section = provider.getSectionAtPosition(document, position);
                if (!section) return null;

                const offsetPosition = provider.getOffsetPosition(section, position);
                const hovers = await vscode.commands.executeCommand<vscode.Hover[]>(
                    'vscode.executeHoverProvider',
                    provider.getVirtualDocumentUri(document.uri, section.language),
                    offsetPosition
                );

                if (!hovers?.[0]) return null;

                // Adjust the hover range if it exists
                const hover = hovers[0];
                if (hover.range) {
                    return new vscode.Hover(
                        hover.contents,
                        new vscode.Range(
                            provider.getOriginalPosition(section, hover.range.start),
                            provider.getOriginalPosition(section, hover.range.end)
                        )
                    );
                }
                return hover;
            }
        }),

        vscode.languages.registerDefinitionProvider('mix', {
            async provideDefinition(document, position, token) {
                console.log(`Definition requested at position: ${position.line}:${position.character}`);
                
                const section = provider.getSectionAtPosition(document, position);
                if (!section) {
                    console.log('No section found at position');
                    return null;
                }

                console.log(`Found section with language: ${section.language}`);
                const offsetPosition = provider.getOffsetPosition(section, position);
                const virtualUri = provider.getVirtualDocumentUri(document.uri, section.language);

                try {
                    console.log(`Executing definition provider for virtual document: ${virtualUri.toString()}`);
                    const locations = await vscode.commands.executeCommand<Array<vscode.Location | vscode.LocationLink>>(
                        'vscode.executeDefinitionProvider',
                        virtualUri,
                        offsetPosition
                    );

                    console.log(`Raw locations:`, locations);

                    if (!locations?.length) return null;

                    const mappedLocations = locations
                        .map(location => {
                            // Handle LocationLink format
                            if ('targetUri' in location) {
                                console.log('Processing LocationLink:', location);
                                const uri = location.targetUri;
                                const range = location.targetRange;
                                return new vscode.Location(uri, range);
                            }
                            // Handle Location format
                            else if ('uri' in location && 'range' in location) {
                                console.log('Processing Location:', location);
                                return location;
                            }
                            console.log('Unknown location format:', location);
                            return null;
                        })
                        .filter((location): location is vscode.Location => {
                            if (!location) {
                                console.log('Filtered out invalid location');
                                return false;
                            }
                            return true;
                        })
                        .map(location => {
                            const uri = location.uri;
                            if (!uri) {
                                console.log('Location missing URI');
                                return null;
                            }

                            console.log(`Processing location in ${uri.toString()}`);

                            // Check if this is a hidden .mix file
                            if (uri.fsPath.includes('/.mix.')) {
                                // Convert back to original .mix file
                                const originalPath = uri.fsPath.replace(/\/\.mix\.[^.]+\.(ts|tsx|js|jsx|php)$/, '.mix');
                                const originalUri = vscode.Uri.file(originalPath);
                                
                                // Find the corresponding section
                                const allSections = provider.sections.get(originalUri.toString()) || [];
                                const targetSection = allSections.find(s => 
                                    provider.getVirtualDocumentUri(originalUri, s.language).toString() === uri.toString()
                                );

                                if (targetSection) {
                                    console.log('Mapping virtual location to original document');
                                    const range = new vscode.Range(
                                        provider.getOriginalPosition(targetSection, location.range.start),
                                        provider.getOriginalPosition(targetSection, location.range.end)
                                    );
                                    return new vscode.Location(originalUri, range);
                                }
                            }
                            
                            // For external files or non-virtual locations
                            return location;
                        })
                        .filter((location): location is vscode.Location => {
                            const valid = location !== null && location !== undefined;
                            if (!valid) console.log('Filtered out null/undefined mapped location');
                            return valid;
                        });

                    console.log(`Returning ${mappedLocations.length} mapped locations`);
                    return mappedLocations;
                } catch (error) {
                    console.error('Definition provider error:', error);
                    return null;
                }
            }
        }),

        vscode.languages.registerReferenceProvider('mix', {
            async provideReferences(document, position, context, token) {
                const section = provider.getSectionAtPosition(document, position);
                if (!section) return null;

                const offsetPosition = provider.getOffsetPosition(section, position);
                const locations = await vscode.commands.executeCommand<vscode.Location[]>(
                    'vscode.executeReferenceProvider',
                    provider.getVirtualDocumentUri(document.uri, section.language),
                    offsetPosition
                );

                if (!locations?.length) return null;

                // Convert virtual document locations back to original document
                const adjustedLocations = locations.map(location => {
                    if (location.uri.scheme === VIRTUAL_SCHEME) {
                        const allSections = provider.sections.get(document.uri.toString()) || [];
                        const targetSection = allSections.find(s => 
                            provider.getVirtualDocumentUri(document.uri, s.language).toString() === location.uri.toString()
                        );

                        if (targetSection) {
                            const range = new vscode.Range(
                                provider.getOriginalPosition(targetSection, location.range.start),
                                provider.getOriginalPosition(targetSection, location.range.end)
                            );
                            return new vscode.Location(document.uri, range);
                        }
                    }
                    return location;
                });

                return adjustedLocations;
            }
        }),
    

        vscode.languages.registerDocumentFormattingEditProvider('mix', {
            async provideDocumentFormattingEdits(document, options, token) {
                const allSections = provider.sections.get(document.uri.toString());
                if (!allSections) return [];

                const edits: vscode.TextEdit[] = [];
                for (const section of allSections) {
                    const virtualUri = provider.getVirtualDocumentUri(document.uri, section.language);
                    const sectionEdits = await vscode.commands.executeCommand<vscode.TextEdit[]>(
                        'vscode.executeFormatDocumentProvider',
                        virtualUri,
                        options
                    );
                    if (sectionEdits) {
                        // Adjust the edit ranges to match the original document
                        const adjustedEdits = sectionEdits.map(edit => {
                            const newRange = new vscode.Range(
                                provider.getOriginalPosition(section, edit.range.start),
                                provider.getOriginalPosition(section, edit.range.end)
                            );
                            return new vscode.TextEdit(newRange, edit.newText);
                        });
                        edits.push(...adjustedEdits);
                    }
                }
                return edits;
            }
        })
    ];

    // Register workspace change watcher
    const watcher = vscode.workspace.createFileSystemWatcher('**/*.mix');
    watcher.onDidChange(async uri => {
        const doc = await vscode.workspace.openTextDocument(uri);
        await provider.initialize(doc);
    });

    // Register all providers and disposables
    context.subscriptions.push(
        provider,
        watcher,
        ...languageFeatures,
        {
            dispose: () => {
                // Clean up all physical files
                for (const [_, virtualDoc] of provider.virtualDocuments) {
                    vscode.workspace.fs.delete(virtualDoc.uri);
                }
            }
        },
        vscode.commands.registerCommand('mix.executeVirtualCommand', 
            (command: string, virtualUri: vscode.Uri, originalUri: vscode.Uri, section: MixSection, ...args: any[]) => {
                return provider.executeVirtualCommand(command, virtualUri, originalUri, section, ...args);
            }
        )
    );

    // Create a temporary directory for virtual documents
    const tmpDir = path.join(os.tmpdir(), 'vscode-mix');
    
    // Register virtual document content provider
    const virtualProvider = new class implements vscode.TextDocumentContentProvider {
        private _onDidChange = new vscode.EventEmitter<vscode.Uri>();
        
        get onDidChange() {
            return this._onDidChange.event;
        }

        provideTextDocumentContent(uri: vscode.Uri): string {
            // Return empty content - we just need the file to exist
            return '';
        }
    };

    // Register the provider
    context.subscriptions.push(
        vscode.workspace.registerTextDocumentContentProvider('mix', virtualProvider)
    );

    // Create the temp files once at startup
    const languages = ['php', 'tsx', 'javascript', 'css', 'html'];
    for (const lang of languages) {
        try {
            const uri = vscode.Uri.parse(`mix:/.mix.${lang}`);
            vscode.workspace.openTextDocument(uri);
        } catch (error) {
            // Ignore errors during initialization
            console.log(`Failed to initialize virtual document for ${lang}:`, error);
        }
    }
}

export function deactivate() {}
