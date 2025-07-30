import React, { useEffect, useRef, useState } from "react";
import { cn } from "@/lib/utils";
import { sanitizeEmailContent } from "@/lib/email-sanitizer";

interface EmailContentRendererProps {
  htmlContent: string;
  className?: string;
  enableIframeIsolation?: boolean;
}

export function EmailContentRenderer({
  htmlContent,
  className,
  enableIframeIsolation = true,
}: EmailContentRendererProps) {
  console.log("EmailContentRenderer received htmlContent:", htmlContent);
  console.log("htmlContent length:", htmlContent ? htmlContent.length : 0);
  console.log("htmlContent type:", typeof htmlContent);
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const [iframeHeight, setIframeHeight] = useState(600);
  
  // Process content once for both iframe and fallback rendering
  const processedContent = React.useMemo(() => {
    console.log('=== EmailContentRenderer Processing ===');
    console.log('Input preview:', htmlContent.substring(0, 200));
    
    // First decode any HTML entities
    let decoded = htmlContent;
    while (decoded.includes('&lt;') || decoded.includes('&gt;') || decoded.includes('&amp;')) {
      const textarea = document.createElement('textarea');
      textarea.innerHTML = decoded;
      const newDecoded = textarea.value;
      if (newDecoded === decoded) break;
      decoded = newDecoded;
    }
    
    console.log('After decoding has <style:', decoded.includes('<style'));
    console.log('Has escaped style tags:', decoded.includes('&lt;style'));
    
    // Check if content might be showing style tags as text
    const visibleStyleIndex = decoded.indexOf('#outlook a{padding:0;}');
    if (visibleStyleIndex !== -1) {
      console.log('Found visible style content at index:', visibleStyleIndex);
      console.log('Context:', decoded.substring(Math.max(0, visibleStyleIndex - 50), visibleStyleIndex + 100));
    }
    
    // Extract style tags BEFORE sanitization
    const styleRegex = /<style[^>]*>([\s\S]*?)<\/style>/gi;
    const styles: string[] = [];
    let match;
    
    while ((match = styleRegex.exec(decoded)) !== null) {
      styles.push(match[0]);
    }
    
    console.log(`Extracted ${styles.length} style tags`);
    if (styles.length > 0) {
      console.log('First style preview:', styles[0].substring(0, 100));
    }
    
    // Sanitize the content (this will remove style tags)
    const sanitized = sanitizeEmailContent(decoded);
    
    console.log('After sanitization has <style:', sanitized.includes('<style'));
    
    return {
      sanitized,
      styles,
      decoded
    };
  }, [htmlContent]);

  useEffect(() => {
    if (!enableIframeIsolation || !iframeRef.current) return;

    const iframe = iframeRef.current;
    const iframeDoc = iframe.contentDocument || iframe.contentWindow?.document;

    if (!iframeDoc) return;
    
    const { sanitized, styles } = processedContent;
    
    // Create the HTML document with email content
    const htmlDocument = `
      <!DOCTYPE html>
      <html>
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          ${styles.join('\n')}
          <style>
            /* Reset styles for email content */
            body {
              margin: 0;
              padding: 16px;
              font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
              font-size: 14px;
              line-height: 1.5;
              color: #333;
              background-color: transparent;
              word-wrap: break-word;
              overflow-wrap: break-word;
            }
            
            /* Ensure images don't overflow */
            img {
              max-width: 100% !important;
              height: auto !important;
            }
            
            /* Ensure tables don't overflow */
            table {
              max-width: 100% !important;
              table-layout: fixed;
              word-wrap: break-word;
            }
            
            /* Handle pre/code blocks */
            pre, code {
              white-space: pre-wrap;
              word-wrap: break-word;
            }
            
            /* Link styling */
            a {
              color: #0066cc;
              text-decoration: underline;
            }
            
            a:hover {
              color: #0052a3;
            }
          </style>
        </head>
        <body>
          ${sanitized}
        </body>
      </html>
    `;

    // Write the content to iframe
    iframeDoc.open();
    iframeDoc.write(htmlDocument);
    iframeDoc.close();
    
    // Debug: Log what's in the iframe
    console.log('Iframe head contains', styles.length, 'style tags');
    console.log('Iframe body preview:', iframeDoc.body.innerHTML.substring(0, 200));

    // Adjust iframe height based on content
    const adjustHeight = () => {
      const body = iframeDoc.body;
      const html = iframeDoc.documentElement;
      
      if (body && html) {
        const height = Math.max(
          body.scrollHeight,
          body.offsetHeight,
          html.clientHeight,
          html.scrollHeight,
          html.offsetHeight
        );
        
        setIframeHeight(height + 32); // Add padding
      }
    };

    // Wait for content to load
    iframe.onload = adjustHeight;
    
    // Also adjust on window resize
    const resizeObserver = new ResizeObserver(adjustHeight);
    if (iframeDoc.body) {
      resizeObserver.observe(iframeDoc.body);
    }

    // Adjust height after images load
    const images = iframeDoc.getElementsByTagName("img");
    Array.from(images).forEach((img) => {
      img.onload = adjustHeight;
    });

    // Initial adjustment
    setTimeout(adjustHeight, 100);

    return () => {
      resizeObserver.disconnect();
    };
  }, [enableIframeIsolation, processedContent]);

  if (enableIframeIsolation) {
    return (
      <iframe
        ref={iframeRef}
        className={cn(
          "w-full border-0 overflow-hidden",
          className
        )}
        height={iframeHeight}
        sandbox="allow-same-origin"
        title="Email Content"
      />
    );
  }

  // Fallback to div rendering (less secure but preserves some styles)
  return (
    <div
      className={cn("email-content", className)}
      dangerouslySetInnerHTML={{ __html: processedContent.sanitized }}
      style={{
        // Reset conflicting styles
        all: "initial",
        fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
        fontSize: "14px",
        lineHeight: "1.5",
        color: "#333",
        display: "block",
      }}
    />
  );
}