import { Extension } from '@tiptap/core';

export const TextIndent = Extension.create({
  name: 'textIndent',
  
  addOptions() {
    return {
      types: ['paragraph', 'heading'],
      indentLevels: 4,
    };
  },

  addGlobalAttributes() {
    return [
      {
        types: this.options.types,
        attributes: {
          indent: {
            default: 0,
            parseHTML: element => {
              const indent = parseInt(element.getAttribute('data-indent') || '0', 10);
              return isNaN(indent) ? { indent: 0 } : { indent };
            },
            renderHTML: attributes => {
              const indent = parseInt(attributes.indent, 10) || 0;
              if (indent <= 0) {
                return {};
              }
              return {
                'data-indent': indent.toString(),
                style: `padding-left: ${indent * 2}em`,
              };
            },
          },
        },
      },
    ];
  },

  addCommands() {
    return {
      setIndent: (indent) => ({ commands, state, dispatch }) => {
        const { selection } = state;
        const { $from } = selection;
        const node = $from.parent;
        
        if (!this.options.types.includes(node.type.name)) {
          return false;
        }
        
        const newIndent = Math.max(0, Math.min(parseInt(indent, 10) || 0, this.options.indentLevels));
        
        if (dispatch) {
          const attrs = { ...node.attrs };
          
          if (newIndent > 0) {
            attrs.indent = newIndent;
          } else {
            delete attrs.indent;
          }
          
          return commands.updateAttributes(node.type, attrs);
        }
        return true;
      },
      
      indent: () => ({ editor, commands }) => {
        const { state } = editor;
        const { selection } = state;
        const { $from } = selection;
        const node = $from.parent;
        
        if (!this.options.types.includes(node.type.name)) {
          return false;
        }
        
        const currentIndent = node.attrs.indent ? parseInt(node.attrs.indent, 10) : 0;
        const newIndent = Math.min(currentIndent + 1, this.options.indentLevels);
        
        if (newIndent !== currentIndent) {
          return commands.setIndent(newIndent);
        }
        return true;
      },
      
      outdent: () => ({ editor, commands }) => {
        const { state } = editor;
        const { selection } = state;
        const { $from } = selection;
        const node = $from.parent;
        
        if (!this.options.types.includes(node.type.name)) {
          return false;
        }
        
        const currentIndent = node.attrs.indent ? parseInt(node.attrs.indent, 10) : 0;
        const newIndent = Math.max(0, currentIndent - 1);
        
        if (newIndent !== currentIndent) {
          return commands.setIndent(newIndent);
        }
        return true;
      },
    };
  },

  addKeyboardShortcuts() {
    return {
      'Tab': () => this.editor.commands.indent(),
      'Shift-Tab': () => this.editor.commands.outdent(),
    };
  },
});

export default TextIndent;
