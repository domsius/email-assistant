import React, { useRef, useEffect, useState, useImperativeHandle, forwardRef } from 'react';
import {
  Bold,
  Italic,
  Underline as UnderlineIcon,
  Link as LinkIcon,
  List,
  ListOrdered,
  Quote,
  Undo,
  Redo,
  AlignLeft,
  AlignCenter,
  AlignRight,
  Image as ImageIcon,
  Sparkles,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';

interface EmailEditorProps {
  content: string;
  onChange: (html: string) => void;
  placeholder?: string;
  className?: string;
  minHeight?: string;
  onGenerateAI?: (context: string) => Promise<string>;
}

export interface EmailEditorRef {
  insertAtCursor: (text: string) => void;
  getSelectedText: () => string;
  getContext: () => { before: string; after: string; selected: string };
}

export const EmailEditor = forwardRef<EmailEditorRef, EmailEditorProps>((
  {
    content,
    onChange,
    placeholder = 'Write your message...',
    className,
    minHeight = '200px',
    onGenerateAI,
  },
  ref
) => {
  const editorRef = useRef<HTMLDivElement>(null);
  const [isEmpty, setIsEmpty] = useState(!content || content === '<p></p>' || content === '<br>');
  const [selection, setSelection] = useState<Range | null>(null);
  const [activeFormats, setActiveFormats] = useState<Set<string>>(new Set());
  const [history, setHistory] = useState<string[]>([]);
  const [historyIndex, setHistoryIndex] = useState(-1);
  const historyTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const [isGeneratingAI, setIsGeneratingAI] = useState(false);
  const [savedRange, setSavedRange] = useState<Range | null>(null);

  // Initialize editor with content
  useEffect(() => {
    if (editorRef.current && editorRef.current.innerHTML !== content) {
      editorRef.current.innerHTML = content || '';
      setIsEmpty(!content || content === '<p></p>' || content === '<br>');
      // Initialize history with initial content
      if (history.length === 0) {
        setHistory([content || '']);
        setHistoryIndex(0);
      }
      
      // Debug logging for signatures
      if (content && content.includes('table')) {
        console.log('=== CONTENTEDITABLE SIGNATURE DEBUG ===');
        console.log('Raw content set:', content);
        
        setTimeout(() => {
          if (editorRef.current) {
            console.log('Rendered HTML:', editorRef.current.innerHTML);
            
            const tables = editorRef.current.querySelectorAll('table');
            console.log(`Found ${tables.length} tables`);
            
            tables.forEach((table, index) => {
              console.log(`Table ${index}:`, table.outerHTML);
              console.log(`Table ${index} computed styles:`, {
                display: window.getComputedStyle(table).display,
                borderCollapse: window.getComputedStyle(table).borderCollapse
              });
              
              const tbody = table.querySelector('tbody');
              if (tbody) {
                console.log(`Table ${index} tbody computed styles:`, {
                  display: window.getComputedStyle(tbody).display
                });
              }
              
              const rows = table.querySelectorAll('tr');
              rows.forEach((row, rowIndex) => {
                console.log(`Row ${rowIndex} computed styles:`, {
                  display: window.getComputedStyle(row).display
                });
              });
            });
          }
        }, 100);
      }
    }
  }, [content]);

  // Check active formats at current cursor position
  const checkActiveFormats = () => {
    const formats = new Set<string>();
    
    // Check if we're in a list or link
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0) {
      let node = sel.getRangeAt(0).startContainer;
      while (node && node !== editorRef.current) {
        if (node.nodeType === Node.ELEMENT_NODE) {
          const element = node as HTMLElement;
          if (element.nodeName === 'UL') formats.add('unorderedList');
          if (element.nodeName === 'OL') formats.add('orderedList');
          if (element.nodeName === 'B' || element.nodeName === 'STRONG') formats.add('bold');
          if (element.nodeName === 'I' || element.nodeName === 'EM') formats.add('italic');
          if (element.nodeName === 'U') formats.add('underline');
          if (element.nodeName === 'BLOCKQUOTE') formats.add('blockquote');
          if (element.nodeName === 'A') formats.add('link');
        }
        node = node.parentNode;
      }
    }
    
    // Check using queryCommandState for more reliable detection
    if (document.queryCommandState('bold')) formats.add('bold');
    if (document.queryCommandState('italic')) formats.add('italic');
    if (document.queryCommandState('underline')) formats.add('underline');
    if (document.queryCommandState('insertUnorderedList')) formats.add('unorderedList');
    if (document.queryCommandState('insertOrderedList')) formats.add('orderedList');
    if (document.queryCommandState('createLink')) formats.add('link');
    
    setActiveFormats(formats);
  };

  // Save selection before toolbar actions
  const saveSelection = () => {
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0) {
      setSelection(sel.getRangeAt(0));
    }
    checkActiveFormats();
  };

  // Restore selection after toolbar actions
  const restoreSelection = () => {
    if (selection) {
      const sel = window.getSelection();
      if (sel) {
        sel.removeAllRanges();
        sel.addRange(selection);
      }
    }
  };

  // Add to history
  const addToHistory = (html: string) => {
    // Don't add if it's the same as current history item
    if (history[historyIndex] === html) {
      return;
    }
    
    // Remove any history items after current index (for when we've undone and then type)
    const newHistory = history.slice(0, historyIndex + 1);
    
    // Add new content to history (limit to 100 items for more granular undo)
    newHistory.push(html);
    if (newHistory.length > 100) {
      // Remove oldest items
      newHistory.splice(0, newHistory.length - 100);
    }
    
    setHistory(newHistory);
    setHistoryIndex(newHistory.length - 1);
  };

  // Handle content changes
  const handleInput = (e: any) => {
    if (editorRef.current) {
      const html = editorRef.current.innerHTML;
      setIsEmpty(!html || html === '<br>' || html === '<p></p>');
      onChange(html);
      checkActiveFormats();
      
      // Check if this is a regular typing event (single character changes)
      const inputType = e.nativeEvent?.inputType;
      
      // For character input, save immediately to history
      if (inputType === 'insertText' || 
          inputType === 'deleteContentBackward' || 
          inputType === 'deleteContentForward' ||
          inputType === 'insertCompositionText') {
        // Add each character change to history immediately
        addToHistory(html);
      } else if (inputType === 'insertParagraph' || 
                 inputType === 'insertLineBreak' ||
                 inputType === 'formatBold' ||
                 inputType === 'formatItalic' ||
                 inputType === 'formatUnderline' ||
                 inputType === 'insertFromPaste' ||
                 inputType === 'insertReplacementText') {
        // For other operations, also save immediately
        addToHistory(html);
      } else {
        // For any other input types, use debouncing as fallback
        if (historyTimeoutRef.current) {
          clearTimeout(historyTimeoutRef.current);
        }
        
        historyTimeoutRef.current = setTimeout(() => {
          addToHistory(html);
        }, 100); // Shorter delay for other operations
      }
    }
  };

  // Execute formatting commands
  const execCommand = (command: string, value?: string) => {
    // Ensure editor has focus
    editorRef.current?.focus();
    
    // Special handling for list commands
    if (command === 'insertUnorderedList' || command === 'insertOrderedList') {
      // For list commands, we need to ensure we're working with block-level content
      const sel = window.getSelection();
      if (sel && sel.rangeCount > 0) {
        const range = sel.getRangeAt(0);
        
        // If the selection is collapsed (no text selected), select the current line/paragraph
        if (range.collapsed && editorRef.current) {
          // Find the current block element
          let node = range.startContainer;
          while (node && node !== editorRef.current) {
            if (node.nodeType === Node.ELEMENT_NODE && 
                (node.nodeName === 'P' || node.nodeName === 'DIV' || node.nodeName === 'LI')) {
              const newRange = document.createRange();
              newRange.selectNodeContents(node);
              sel.removeAllRanges();
              sel.addRange(newRange);
              break;
            }
            node = node.parentNode;
          }
        }
      }
    } else {
      // For other commands, ensure we have a selection
      const sel = window.getSelection();
      if (!sel || sel.rangeCount === 0) {
        // If no selection, select all content for commands to work
        if (editorRef.current && editorRef.current.childNodes.length > 0) {
          const range = document.createRange();
          range.selectNodeContents(editorRef.current);
          sel?.removeAllRanges();
          sel?.addRange(range);
        }
      }
    }
    
    // Execute the command
    const result = document.execCommand(command, false, value);
    
    // Get the new content and update
    if (editorRef.current) {
      const html = editorRef.current.innerHTML;
      onChange(html);
      checkActiveFormats();
      
      // Add to history immediately for formatting commands
      addToHistory(html);
    }
    
    // Keep focus on editor
    editorRef.current?.focus();
    
    return result;
  };

  // Handle paste to preserve HTML
  const handlePaste = (e: React.ClipboardEvent) => {
    e.preventDefault();
    const html = e.clipboardData.getData('text/html');
    const text = e.clipboardData.getData('text/plain');
    
    // Prefer HTML to preserve formatting, especially for signatures
    if (html) {
      document.execCommand('insertHTML', false, html);
    } else if (text) {
      document.execCommand('insertText', false, text);
    }
    
    // Update and save to history immediately after paste
    if (editorRef.current) {
      const newHtml = editorRef.current.innerHTML;
      onChange(newHtml);
      checkActiveFormats();
      addToHistory(newHtml);
    }
  };

  const addLink = () => {
    // Save current selection first
    saveSelection();
    
    // Get the current selection
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) {
      alert('Please select some text first to create a link');
      return;
    }
    
    const selectedText = sel.toString();
    
    // Check if selection is already a link
    let node = sel.getRangeAt(0).startContainer;
    let isLink = false;
    while (node && node !== editorRef.current) {
      if (node.nodeType === Node.ELEMENT_NODE && node.nodeName === 'A') {
        isLink = true;
        break;
      }
      node = node.parentNode;
    }
    
    if (isLink) {
      // Remove link
      execCommand('unlink');
      return;
    }
    
    if (!selectedText) {
      alert('Please select some text first to create a link');
      return;
    }
    
    const url = window.prompt('Enter URL:', 'https://');
    if (url && url.trim() && url !== 'https://') {
      // Restore selection before executing command
      restoreSelection();
      
      // If URL doesn't have protocol, add https://
      const finalUrl = url.match(/^https?:\/\//) ? url : `https://${url}`;
      
      try {
        new URL(finalUrl); // Validate URL
        execCommand('createLink', finalUrl);
      } catch (error) {
        alert('Please enter a valid URL');
        console.error('Invalid URL provided:', error);
      }
    }
  };

  const addImage = () => {
    const url = window.prompt('Enter image URL:');
    if (url && url.trim()) {
      try {
        new URL(url); // Validate URL
        execCommand('insertImage', url);
      } catch (error) {
        console.error('Invalid image URL provided:', error);
      }
    }
  };

  // Insert text at cursor position
  const insertAtCursor = (text: string) => {
    editorRef.current?.focus();
    
    // Try to restore saved range if available
    if (savedRange) {
      const sel = window.getSelection();
      if (sel) {
        sel.removeAllRanges();
        sel.addRange(savedRange);
      }
    }
    
    // Insert the text
    document.execCommand('insertHTML', false, text);
    
    // Update content
    if (editorRef.current) {
      const html = editorRef.current.innerHTML;
      onChange(html);
      setIsEmpty(!html || html === '<br>' || html === '<p></p>');
      addToHistory(html);
    }
  };

  // Get selected text
  const getSelectedText = (): string => {
    const sel = window.getSelection();
    return sel ? sel.toString() : '';
  };

  // Get context around cursor
  const getContext = (): { before: string; after: string; selected: string } => {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0 || !editorRef.current) {
      return { before: '', after: '', selected: '' };
    }

    const range = sel.getRangeAt(0);
    const selected = sel.toString();
    
    // Get text before cursor (up to 500 chars)
    const beforeRange = document.createRange();
    beforeRange.setStart(editorRef.current, 0);
    beforeRange.setEnd(range.startContainer, range.startOffset);
    const beforeText = beforeRange.toString().slice(-500);
    
    // Get text after cursor (up to 500 chars)
    const afterRange = document.createRange();
    afterRange.setStart(range.endContainer, range.endOffset);
    afterRange.setEndAfter(editorRef.current.lastChild || editorRef.current);
    const afterText = afterRange.toString().slice(0, 500);
    
    return { before: beforeText, after: afterText, selected };
  };

  // Handle AI generation
  const handleGenerateAI = async () => {
    if (!onGenerateAI || isGeneratingAI) return;
    
    setIsGeneratingAI(true);
    
    // Save current selection/cursor position
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0) {
      setSavedRange(sel.getRangeAt(0).cloneRange());
    }
    
    try {
      const context = getContext();
      const contextString = `${context.before}[CURSOR]${context.selected}[/CURSOR]${context.after}`;
      
      // Call the AI generation function
      const generatedText = await onGenerateAI(contextString);
      
      if (generatedText) {
        // If there's selected text, replace it; otherwise insert at cursor
        if (context.selected) {
          document.execCommand('delete', false);
        }
        insertAtCursor(generatedText);
      }
    } catch (error) {
      console.error('AI generation failed:', error);
    } finally {
      setIsGeneratingAI(false);
    }
  };

  // Expose methods to parent via ref
  useImperativeHandle(ref, () => ({
    insertAtCursor,
    getSelectedText,
    getContext,
  }));

  // Custom undo function
  const handleUndo = () => {
    if (historyIndex > 0) {
      const newIndex = historyIndex - 1;
      const previousContent = history[newIndex];
      
      if (editorRef.current) {
        editorRef.current.innerHTML = previousContent;
        setHistoryIndex(newIndex);
        onChange(previousContent);
        setIsEmpty(!previousContent || previousContent === '<br>' || previousContent === '<p></p>');
        checkActiveFormats();
      }
    }
  };

  // Custom redo function
  const handleRedo = () => {
    if (historyIndex < history.length - 1) {
      const newIndex = historyIndex + 1;
      const nextContent = history[newIndex];
      
      if (editorRef.current) {
        editorRef.current.innerHTML = nextContent;
        setHistoryIndex(newIndex);
        onChange(nextContent);
        setIsEmpty(!nextContent || nextContent === '<br>' || nextContent === '<p></p>');
        checkActiveFormats();
      }
    }
  };

  return (
    <div className="overflow-hidden border rounded-md">
      {/* Toolbar */}
      <div className="border-b p-2 bg-background">
        <div className="flex flex-wrap items-center gap-1">
          {/* Font Family */}
          <Select
            onValueChange={(value) => execCommand('fontName', value)}
          >
            <SelectTrigger className="w-[130px] h-8 text-xs">
              <SelectValue placeholder="Font" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="Arial">Arial</SelectItem>
              <SelectItem value="Times New Roman">Times New Roman</SelectItem>
              <SelectItem value="Courier New">Courier New</SelectItem>
              <SelectItem value="Georgia">Georgia</SelectItem>
              <SelectItem value="Verdana">Verdana</SelectItem>
            </SelectContent>
          </Select>

          {/* Font Size */}
          <Select
            onValueChange={(value) => {
              // Use formatBlock for heading sizes or fontSize for legacy support
              if (value.startsWith('h')) {
                execCommand('formatBlock', value);
              } else {
                execCommand('fontSize', value);
              }
            }}
          >
            <SelectTrigger className="w-[100px] h-8 text-xs">
              <SelectValue placeholder="Size" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="1">Tiny</SelectItem>
              <SelectItem value="2">Small</SelectItem>
              <SelectItem value="3">Normal</SelectItem>
              <SelectItem value="4">Medium</SelectItem>
              <SelectItem value="5">Large</SelectItem>
              <SelectItem value="6">Extra Large</SelectItem>
              <SelectItem value="7">Huge</SelectItem>
            </SelectContent>
          </Select>

          <Separator orientation="vertical" className="h-6" />

          {/* Text Formatting */}
          <Button
            variant={activeFormats.has('bold') ? 'secondary' : 'ghost'}
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => execCommand('bold')}
            className="h-8 w-8 p-0"
            title="Bold (Ctrl+B)"
          >
            <Bold className="h-4 w-4" />
          </Button>
          <Button
            variant={activeFormats.has('italic') ? 'secondary' : 'ghost'}
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => execCommand('italic')}
            className="h-8 w-8 p-0"
            title="Italic (Ctrl+I)"
          >
            <Italic className="h-4 w-4" />
          </Button>
          <Button
            variant={activeFormats.has('underline') ? 'secondary' : 'ghost'}
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => execCommand('underline')}
            className="h-8 w-8 p-0"
            title="Underline (Ctrl+U)"
          >
            <UnderlineIcon className="h-4 w-4" />
          </Button>

          <Separator orientation="vertical" className="h-6" />

          {/* Text Color */}
          <input
            type="color"
            onChange={(e) => execCommand('foreColor', e.target.value)}
            className="h-8 w-8 cursor-pointer rounded"
            title="Text color"
          />

          <Separator orientation="vertical" className="h-6" />

          {/* Alignment */}
          <Button
            variant="ghost"
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => execCommand('justifyLeft')}
            className="h-8 w-8 p-0"
          >
            <AlignLeft className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => execCommand('justifyCenter')}
            className="h-8 w-8 p-0"
          >
            <AlignCenter className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => execCommand('justifyRight')}
            className="h-8 w-8 p-0"
          >
            <AlignRight className="h-4 w-4" />
          </Button>

          <Separator orientation="vertical" className="h-6" />

          {/* Lists */}
          <Button
            variant={activeFormats.has('unorderedList') ? 'secondary' : 'ghost'}
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => execCommand('insertUnorderedList')}
            className="h-8 w-8 p-0"
            title="Bullet list"
          >
            <List className="h-4 w-4" />
          </Button>
          <Button
            variant={activeFormats.has('orderedList') ? 'secondary' : 'ghost'}
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => execCommand('insertOrderedList')}
            className="h-8 w-8 p-0"
            title="Numbered list"
          >
            <ListOrdered className="h-4 w-4" />
          </Button>

          <Separator orientation="vertical" className="h-6" />

          {/* Link & Image */}
          <Button
            variant={activeFormats.has('link') ? 'secondary' : 'ghost'}
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={addLink}
            className="h-8 w-8 p-0"
            title={activeFormats.has('link') ? 'Remove link' : 'Add link'}
          >
            <LinkIcon className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={addImage}
            className="h-8 w-8 p-0"
          >
            <ImageIcon className="h-4 w-4" />
          </Button>

          <Separator orientation="vertical" className="h-6" />

          {/* AI Generate */}
          {onGenerateAI && (
            <>
              <Button
                variant={isGeneratingAI ? 'secondary' : 'ghost'}
                size="sm"
                onMouseDown={(e) => e.preventDefault()}
                onClick={handleGenerateAI}
                className="h-8 gap-1 px-2"
                disabled={isGeneratingAI}
                title="Generate with AI (at cursor position)"
              >
                <Sparkles className="h-4 w-4" />
                <span className="text-xs">{isGeneratingAI ? 'Generating...' : 'AI'}</span>
              </Button>
              <Separator orientation="vertical" className="h-6" />
            </>
          )}

          {/* Quote */}
          <Button
            variant="ghost"
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => execCommand('formatBlock', 'blockquote')}
            className="h-8 w-8 p-0"
          >
            <Quote className="h-4 w-4" />
          </Button>

          <Separator orientation="vertical" className="h-6" />

          {/* Undo/Redo */}
          <Button
            variant="ghost"
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={handleUndo}
            className="h-8 w-8 p-0"
            disabled={historyIndex <= 0}
            title="Undo (Ctrl+Z)"
          >
            <Undo className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={handleRedo}
            className="h-8 w-8 p-0"
            disabled={historyIndex >= history.length - 1}
            title="Redo (Ctrl+Y)"
          >
            <Redo className="h-4 w-4" />
          </Button>
        </div>
      </div>

      {/* Editor */}
      <div
        ref={editorRef}
        contentEditable
        onInput={handleInput}
        onPaste={handlePaste}
        onFocus={saveSelection}
        onMouseUp={saveSelection}
        onKeyUp={saveSelection}
        onKeyDown={(e) => {
          // Handle keyboard shortcuts
          if (e.ctrlKey || e.metaKey) {
            if (e.key === 'z' && !e.shiftKey) {
              e.preventDefault();
              handleUndo();
            } else if ((e.key === 'y') || (e.key === 'z' && e.shiftKey)) {
              e.preventDefault();
              handleRedo();
            }
          }
        }}
        onClick={(e) => {
          // Allow clicking links with Ctrl/Cmd held
          if ((e.ctrlKey || e.metaKey) && e.target instanceof HTMLAnchorElement) {
            e.preventDefault();
            window.open(e.target.href, '_blank');
          }
        }}
        className={cn(
          'min-h-[200px] p-4 focus:outline-none',
          'prose prose-sm max-w-none',
          '[&>*:first-child]:mt-0',
          // Ensure proper rendering of font styles
          '[&_font]:!inline',
          '[&_span]:!inline',
          // Hyperlink styles - blue with underline
          '[&_a]:text-blue-600 [&_a]:underline [&_a]:cursor-pointer',
          '[&_a:hover]:text-blue-800 [&_a:hover]:decoration-2',
          'dark:[&_a]:text-blue-400 dark:[&_a:hover]:text-blue-300',
          // List styles - ensure lists are visible
          '[&_ul]:list-disc [&_ul]:ml-6 [&_ul]:my-2',
          '[&_ol]:list-decimal [&_ol]:ml-6 [&_ol]:my-2',
          '[&_li]:ml-2 [&_li]:my-1',
          // Nested lists
          '[&_ul_ul]:list-circle [&_ul_ul]:ml-4',
          '[&_ol_ol]:list-lower-alpha [&_ol_ol]:ml-4',
          // Blockquote styles
          '[&_blockquote]:border-l-4 [&_blockquote]:border-gray-300 [&_blockquote]:pl-4 [&_blockquote]:italic',
          isEmpty && 'text-muted-foreground',
          className
        )}
        style={{ minHeight }}
        suppressContentEditableWarning
        data-placeholder={placeholder}
        // Enable spell check
        spellCheck={true}
      />
      
      {/* Show placeholder */}
      {isEmpty && (
        <div className="absolute pointer-events-none p-4 text-muted-foreground">
          {placeholder}
        </div>
      )}
    </div>
  );
});

EmailEditor.displayName = 'EmailEditor';

// Export for backward compatibility
export default EmailEditor;