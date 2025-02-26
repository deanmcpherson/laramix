
import fs from 'fs/promises';
import path from 'path';


/**
 * Vite plugin for handling .mix files with multiple language sections
 * @returns A Vite plugin
 */
export default function mixPlugin() {
  return {
    name: 'vite-plugin-mix',
    //@ts-ignore
    async resolveId(id, importer) {
      // Check if this is a .mix file with a language query
      if (!id.includes('.mix')) return null;
      let filePath = id;
      let query = '';
      if (id.endsWith('.mix')) {
        filePath = id;
        query = 'tsx|ts|jsx|js';
      } else {
        [filePath, query] = id.split('?', 2);
      }
      if (!filePath.endsWith('.mix')) return null;
      
      // If we have an importer, resolve the file path relative to it
      if (importer) {
        const resolvedPath = path.resolve(path.dirname(importer), filePath);
        return `${resolvedPath}?${query}`;
      }
      
      return id;
    },
    //@ts-ignore
    async load(id) {
      // Check if this is a .mix file with a language query
      if (!id.includes('.mix')) return null;
      console.log('id', id);
      let filePath = id;
      let query = '';
      if (id.endsWith('.mix')) {
        filePath = id;
        query = 'tsx|ts|jsx|js';
      } else {
        [filePath, query] = id.split('?', 2);
      }
      if (!filePath.endsWith('.mix')) return null;
      

      try {
        
        // Read the file content
        const content = await fs.readFile(filePath, 'utf-8');
        
        // Extract the requested language section
        const sections = parseMixFile(content);
        
        const targetLanguages = query.split('|').map(
          normalizeLanguage
        );
        let targetLanguage = '';
        // Find all sections matching the requested language
        const matchingSections = sections.filter(section => 
          {
            const isMatch =targetLanguages.includes(normalizeLanguage(section.language));
            if (isMatch) {
              if (targetLanguage && targetLanguage !== section.language) {
                throw new Error(`Multiple languages found in ${filePath}: ${targetLanguage} and ${section.language}. Add a ?language to the end of the import to specify the language.`);
              }
              targetLanguage = section.language;
            }
            return isMatch;
          }
        );
        
        if (matchingSections.length === 0) {
          console.warn(`No ${query} sections found in ${filePath}`);
          return '';
        }
        
        // Combine all matching sections
        const combinedContent = matchingSections.map(section => section.content).join('\n\n');
        // Return the content with appropriate transformations based on language
        return transformContent(combinedContent, normalizeLanguage(targetLanguage));
      } catch (error) {
        console.error(`Error processing ${filePath}:`, error);
        return null;
      }
    }
  };
}

/**
 * Parse a .mix file into language sections
 */
function parseMixFile(content) {
  const sections = [];
  const mixRegex = /<mix\s+lang="([^"]+)">([\s\S]*?)<\/mix>/g;
  
  let match;
  while ((match = mixRegex.exec(content)) !== null) {
    sections.push({
      language: match[1],
      content: match[2].trim()
    });
  }
  
  return sections;
}

/**
 * Normalize language identifiers to handle aliases
 */
function normalizeLanguage(language) {
  const languageMap = {
    'js': 'javascript',
    'jsx': 'javascriptreact',
    'ts': 'typescript',
    'tsx': 'typescriptreact',
    'py': 'python',
    // Add more aliases as needed
  };
  
  return languageMap[language] || language;
}

/**
 * Transform content based on language type
 */
function transformContent(content, language) {
  switch (language) {
    case 'css':
      // For CSS, return as a module with default export
      return `const css = ${JSON.stringify(content)};\nexport default css;`;
      
    case 'html':
      // For HTML, return as a string
      return `export default ${JSON.stringify(content)};`;
      
    case 'javascript':
    case 'javascriptreact':
    case 'typescript':
    case 'typescriptreact':
      // For JS/TS, return the raw content
      return content;
      
    case 'php':
    case 'python':
      // For server languages, return as a string
      return `export default ${JSON.stringify(content)};`;
      
    default:
      // Default behavior: return as a string
      return `export default ${JSON.stringify(content)};`;
  }
} 