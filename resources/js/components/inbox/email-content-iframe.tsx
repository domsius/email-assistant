import React, { useEffect, useRef, useState } from 'react';
import DOMPurify from 'dompurify';

interface EmailContentIframeProps {
  content: string;
  className?: string;
}

export function EmailContentIframe({ content, className = '' }: EmailContentIframeProps) {
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const [iframeHeight, setIframeHeight] = useState<number>(100);

  useEffect(() => {
    if (!iframeRef.current || !content) return;

    const iframe = iframeRef.current;
    const iframeDoc = iframe.contentDocument || iframe.contentWindow?.document;
    
    if (!iframeDoc) return;

    // Sanitize content before injecting
    const cleanHTML = DOMPurify.sanitize(content, {
      ADD_TAGS: ['style'],
      ADD_ATTR: ['style', 'class', 'bgcolor', 'background', 'align', 'valign', 'width', 'height'],
      ALLOW_DATA_ATTR: false,
      FORBID_TAGS: ['script', 'iframe', 'object', 'embed', 'form'],
      FORBID_ATTR: ['onerror', 'onload', 'onclick', 'onmouseover'],
    });

    // Create the iframe content with isolated styles
    const iframeContent = `
      <!DOCTYPE html>
      <html>
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <style>
            /* Reset and base styles for email content */
            * {
              margin: 0;
              padding: 0;
              box-sizing: border-box;
            }
            
            body {
              font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
              font-size: 14px;
              line-height: 1.6;
              color: #333;
              background: transparent;
              padding: 0;
              margin: 0;
              word-wrap: break-word;
              overflow-wrap: break-word;
            }
            
            /* Basic typography */
            h1, h2, h3, h4, h5, h6 {
              margin: 0.5em 0;
              font-weight: 600;
            }
            
            p {
              margin: 0.5em 0;
            }
            
            a {
              color: #0066cc;
              text-decoration: none;
            }
            
            a:hover {
              text-decoration: underline;
            }
            
            img {
              max-width: 100%;
              height: auto;
              display: block;
            }
            
            table {
              max-width: 100%;
              border-collapse: collapse;
            }
            
            /* Common email styles */
            blockquote {
              margin: 1em 0;
              padding-left: 1em;
              border-left: 3px solid #ddd;
              color: #666;
            }
            
            pre {
              background: #f5f5f5;
              padding: 0.5em;
              border-radius: 3px;
              overflow-x: auto;
            }
            
            code {
              background: #f5f5f5;
              padding: 0.2em 0.4em;
              border-radius: 3px;
              font-family: 'Courier New', monospace;
              font-size: 0.9em;
            }
            
            /* Lists */
            ul, ol {
              margin: 0.5em 0;
              padding-left: 1.5em;
            }
            
            li {
              margin: 0.25em 0;
            }
            
            /* Gmail quote styling */
            .gmail_quote {
              margin: 1em 0;
              padding-left: 1em;
              border-left: 2px solid #ccc;
              color: #666;
            }
            
            /* Outlook quote styling */
            .ExternalClass {
              width: 100%;
            }
            
            /* Responsive design */
            @media (max-width: 600px) {
              body {
                font-size: 13px;
              }
              
              table {
                width: 100% !important;
              }
            }
          </style>
        </head>
        <body>
          <div class="email-body">
            ${cleanHTML}
          </div>
        </body>
      </html>
    `;

    // Write the content to iframe
    iframeDoc.open();
    iframeDoc.write(iframeContent);
    iframeDoc.close();

    // Auto-resize iframe to fit content
    const updateHeight = () => {
      if (iframeDoc.body) {
        const newHeight = iframeDoc.body.scrollHeight;
        setIframeHeight(newHeight + 20); // Add some padding
      }
    };

    // Initial height setting
    updateHeight();

    // Watch for content changes
    const observer = new MutationObserver(updateHeight);
    if (iframeDoc.body) {
      observer.observe(iframeDoc.body, {
        childList: true,
        subtree: true,
        attributes: true,
      });
    }

    // Handle images loading
    const images = iframeDoc.querySelectorAll('img');
    images.forEach(img => {
      img.addEventListener('load', updateHeight);
      img.addEventListener('error', updateHeight);
    });

    // Cleanup
    return () => {
      observer.disconnect();
      images.forEach(img => {
        img.removeEventListener('load', updateHeight);
        img.removeEventListener('error', updateHeight);
      });
    };
  }, [content]);

  return (
    <iframe
      ref={iframeRef}
      className={`w-full border-0 ${className}`}
      style={{ 
        height: `${iframeHeight}px`,
        minHeight: '100px',
        display: 'block'
      }}
      title="Email Content"
      sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
    />
  );
}