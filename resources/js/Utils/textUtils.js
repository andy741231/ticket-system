/**
 * Converts URLs in text to clickable links
 * @param {string} text - The input text that may contain URLs
 * @returns {string} Text with URLs converted to HTML anchor tags
 */
export function linkify(text) {
    if (!text) return '';
    
    // URL regex pattern
    const urlPattern = /(https?:\/\/[^\s<]+[^\s<.,)\]])/g;
    
    // Replace URLs with anchor tags
    return text.replace(urlPattern, (url) => {
        // Ensure URL has a protocol
        const href = url.match(/^https?:\/\//) ? url : `https://${url}`;
        return `<a href="${href}" target="_blank" rel="noopener noreferrer" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 underline transition-colors">${url}</a>`;
    });
}

/**
 * Renders @mentions in text with styling
 * @param {string} text - The text containing mentions
 * @returns {string} Text with styled mentions
 */
export function renderMentions(text) {
    if (!text) return '';
    
    // Only wrap the username token immediately after '@' (stop at first whitespace)
    return text.replace(/@([a-zA-Z0-9_\-]+)/g, (match, username) => {
        return `<span class="mention bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 px-1 rounded font-medium">${match}</span>`;
    });
}

/**
 * Safely renders HTML content with clickable URLs and styled mentions
 * @param {string} html - The HTML content to render
 * @returns {string} HTML with URLs converted to clickable links and mentions styled
 */
export function renderWithLinks(html) {
    if (!html) return '';
    
    // Create a temporary div to parse the HTML
    const div = document.createElement('div');
    div.innerHTML = html;
    
    // Process text nodes to find and replace URLs and mentions
    const walker = document.createTreeWalker(
        div,
        NodeFilter.SHOW_TEXT,
        null,
        false
    );
    
    const textNodes = [];
    let node;
    
    // Collect all text nodes
    while (node = walker.nextNode()) {
        // Skip empty or whitespace-only nodes
        if (node.nodeValue.trim() === '') continue;
        textNodes.push(node);
    }
    
    // Process each text node
    for (const textNode of textNodes) {
        const parent = textNode.parentNode;
        
        // Skip if parent is already a link or mention
        if (parent.tagName === 'A' || parent.classList?.contains('mention')) continue;
        
        // Create a temporary span to hold the processed HTML
        const temp = document.createElement('span');
        let processedText = textNode.nodeValue;
        
        // First process URLs
        processedText = linkify(processedText);
        
        // Then process mentions
        processedText = renderMentions(processedText);
        
        temp.innerHTML = processedText;
        
        // Only replace if we actually found URLs or mentions
        if (temp.innerHTML !== textNode.nodeValue) {
            // Replace text node with processed content
            while (temp.firstChild) {
                parent.insertBefore(temp.firstChild, textNode);
            }
            parent.removeChild(textNode);
        }
    }
    
    return div.innerHTML;
}
