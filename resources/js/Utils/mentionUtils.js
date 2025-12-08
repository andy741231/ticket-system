/**
 * Utility functions for handling @mentions in comments
 */

/**
 * Extract @mentions from text
 * @param {string} text - The text to parse for mentions
 * @returns {Array} Array of mentioned usernames (without @)
 */
export function extractMentions(text) {
    if (!text) return [];
    // Match @username or @First Last (single space only)
    const mentionPattern = /@([a-zA-Z0-9_\-]+(?:\s+[a-zA-Z0-9_\-]+)?)/g;
    const mentions = [];
    let match;
    
    while ((match = mentionPattern.exec(text)) !== null) {
        const username = match[1];
        if (!mentions.includes(username)) {
            mentions.push(username);
        }
    }
    
    return mentions;
}

/**
 * Replace @mentions in text with styled spans
 * @param {string} text - The text containing mentions
 * @param {Array} validUsers - Array of user objects with valid usernames
 * @returns {string} HTML with styled mentions
 */
export function renderMentions(text, validUsers = []) {
    if (!text) return '';
    
    const validUsernames = validUsers.map(user => user.username || user.name).filter(Boolean);
    
    return text.replace(/@([a-zA-Z0-9_\-]+(?:\s+[a-zA-Z0-9_\-]+)?)/g, (match, username) => {
        // Check if this is a valid user who has access to the ticket
        const isValid = validUsernames.some(validUsername => 
            validUsername.toLowerCase() === username.toLowerCase()
        );
        
        if (isValid) {
            return `<span class="mention-valid bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 px-1 rounded font-medium">${match}</span>`;
        } else {
            return `<span class="mention-invalid text-gray-500 dark:text-gray-400">${match}</span>`;
        }
    });
}

/**
 * Search for users by username/name for autocomplete
 * @param {string} query - The search query
 * @param {Array} availableUsers - Array of users available for mentioning
 * @returns {Array} Filtered users matching the query
 */
export function searchUsers(query, availableUsers = []) {
    if (!query || query.length < 1) return [];
    
    const searchTerm = query.toLowerCase();
    
    return availableUsers.filter(user => {
        // Search in all searchable names
        if (user.searchable_names) {
            return user.searchable_names.some(name => 
                name && name.toLowerCase().includes(searchTerm)
            );
        }
        
        // Fallback to basic search
        const username = (user.username || user.name || '').toLowerCase();
        const email = (user.email || '').toLowerCase();
        const firstName = (user.first_name || '').toLowerCase();
        const lastName = (user.last_name || '').toLowerCase();
        
        return username.includes(searchTerm) || 
               email.includes(searchTerm) ||
               firstName.includes(searchTerm) ||
               lastName.includes(searchTerm);
    }).slice(0, 20); // Limit to 20 results
}

/**
 * Get cursor position for mention autocomplete
 * @param {HTMLTextAreaElement} textarea - The textarea element
 * @returns {Object|null} Object with mention info or null if no mention at cursor
 */
export function getMentionAtCursor(textarea) {
    const cursorPos = textarea.selectionStart;
    const textBeforeCursor = textarea.value.substring(0, cursorPos);

    // Find the index of the last '@' before the cursor
    const atIndex = textBeforeCursor.lastIndexOf('@');
    if (atIndex === -1) return null;

    // Ensure '@' is at start or preceded by whitespace
    if (atIndex > 0) {
        const prevChar = textBeforeCursor[atIndex - 1];
        if (!/\s/.test(prevChar)) return null;
    }

    // Extract token from '@' up to the first whitespace or end of textBeforeCursor
    const afterAt = textBeforeCursor.substring(atIndex + 1);
    // Allow empty token so typing just '@' opens the dropdown
    const tokenMatch = afterAt.match(/^([a-zA-Z0-9_\-]*)/); // stop at first whitespace
    if (!tokenMatch) return {
        start: atIndex,
        end: atIndex + 1,
        query: '',
        fullMatch: '@',
    };

    const token = tokenMatch[1] ?? '';
    return {
        start: atIndex,
        end: atIndex + 1 + token.length,
        query: token,
        fullMatch: '@' + token,
    };
}

/**
 * Replace mention text in textarea
 * @param {HTMLTextAreaElement} textarea - The textarea element
 * @param {Object} mentionInfo - Mention info from getMentionAtCursor
 * @param {string} username - The username to insert
 */
export function replaceMention(textarea, mentionInfo, username) {
    const beforeMention = textarea.value.substring(0, mentionInfo.start);
    const afterMention = textarea.value.substring(mentionInfo.end);
    
    const newValue = beforeMention + `@${username} ` + afterMention;
    textarea.value = newValue;
    
    // Set cursor position after the inserted mention
    const newCursorPos = mentionInfo.start + username.length + 2; // +2 for @ and space
    textarea.setSelectionRange(newCursorPos, newCursorPos);
    
    // Trigger input event to update Vue model
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}
