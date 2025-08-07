import React, { useRef, useEffect, useState } from 'react';
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
}

export function EmailEditor({
  content,
  onChange,
  placeholder = 'Write your message...',
  className,
  minHeight = '200px',
}: EmailEditorProps) {
  const editorRef = useRef<HTMLDivElement>(null);
  const [isEmpty, setIsEmpty] = useState(!content || content === '<p></p>' || content === '<br>');
  const [selection, setSelection] = useState<Range | null>(null);

  // Initialize editor with content
  useEffect(() => {
    if (editorRef.current && editorRef.current.innerHTML !== content) {
      editorRef.current.innerHTML = content || '';
      setIsEmpty(!content || content === '<p></p>' || content === '<br>');
      
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

  // Save selection before toolbar actions
  const saveSelection = () => {
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0) {
      setSelection(sel.getRangeAt(0));
    }
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

  // Handle content changes
  const handleInput = () => {
    if (editorRef.current) {
      const html = editorRef.current.innerHTML;
      setIsEmpty(!html || html === '<br>' || html === '<p></p>');
      onChange(html);
    }
  };

  // Execute formatting commands
  const execCommand = (command: string, value?: string) => {
    saveSelection();
    editorRef.current?.focus();
    restoreSelection();
    document.execCommand(command, false, value);
    handleInput();
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
    handleInput();
  };

  const addLink = () => {
    const url = window.prompt('Enter URL:');
    if (url && url.trim()) {
      try {
        new URL(url); // Validate URL
        execCommand('createLink', url);
      } catch (error) {
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
            onValueChange={(value) => execCommand('fontSize', value)}
          >
            <SelectTrigger className="w-[100px] h-8 text-xs">
              <SelectValue placeholder="Size" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="1">Small</SelectItem>
              <SelectItem value="3">Normal</SelectItem>
              <SelectItem value="5">Large</SelectItem>
              <SelectItem value="7">Huge</SelectItem>
            </SelectContent>
          </Select>

          <Separator orientation="vertical" className="h-6" />

          {/* Text Formatting */}
          <Button
            variant="ghost"
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => execCommand('bold')}
            className="h-8 w-8 p-0"
          >
            <Bold className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => execCommand('italic')}
            className="h-8 w-8 p-0"
          >
            <Italic className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => execCommand('underline')}
            className="h-8 w-8 p-0"
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
            variant="ghost"
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => execCommand('insertUnorderedList')}
            className="h-8 w-8 p-0"
          >
            <List className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => execCommand('insertOrderedList')}
            className="h-8 w-8 p-0"
          >
            <ListOrdered className="h-4 w-4" />
          </Button>

          <Separator orientation="vertical" className="h-6" />

          {/* Link & Image */}
          <Button
            variant="ghost"
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={addLink}
            className="h-8 w-8 p-0"
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
            onClick={() => execCommand('undo')}
            className="h-8 w-8 p-0"
          >
            <Undo className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => execCommand('redo')}
            className="h-8 w-8 p-0"
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
        className={cn(
          'min-h-[200px] p-4 focus:outline-none',
          'prose prose-sm max-w-none',
          '[&>*:first-child]:mt-0',
          isEmpty && 'text-muted-foreground',
          className
        )}
        style={{ minHeight }}
        suppressContentEditableWarning
        data-placeholder={placeholder}
      />
      
      {/* Show placeholder */}
      {isEmpty && (
        <div className="absolute pointer-events-none p-4 text-muted-foreground">
          {placeholder}
        </div>
      )}
    </div>
  );
}

// Export for backward compatibility
export default EmailEditor;