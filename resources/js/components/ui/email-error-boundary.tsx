import React, { Component, ErrorInfo, ReactNode } from "react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { AlertCircle } from "lucide-react";
import { Button } from "@/components/ui/button";

interface EmailErrorBoundaryProps {
  children: ReactNode;
  fallback?: ReactNode;
  onError?: (error: Error, errorInfo: ErrorInfo) => void;
}

interface EmailErrorBoundaryState {
  hasError: boolean;
  error: Error | null;
}

export class EmailErrorBoundary extends Component<
  EmailErrorBoundaryProps,
  EmailErrorBoundaryState
> {
  constructor(props: EmailErrorBoundaryProps) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error: Error): EmailErrorBoundaryState {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    console.error("Email preview error:", error, errorInfo);
    
    // Call the optional error handler
    if (this.props.onError) {
      this.props.onError(error, errorInfo);
    }
  }

  handleReset = () => {
    this.setState({ hasError: false, error: null });
  };

  render() {
    if (this.state.hasError) {
      // Use custom fallback if provided
      if (this.props.fallback) {
        return <>{this.props.fallback}</>;
      }

      // Default error UI
      return (
        <Alert variant="destructive" className="m-4">
          <AlertCircle className="h-4 w-4" />
          <AlertTitle>Error loading email</AlertTitle>
          <AlertDescription className="mt-2 space-y-2">
            <p>
              There was an error rendering this email. This might be due to
              malformed HTML content or an unexpected error.
            </p>
            {process.env.NODE_ENV === "development" && this.state.error && (
              <details className="mt-2">
                <summary className="cursor-pointer text-sm font-medium">
                  Error details
                </summary>
                <pre className="mt-2 text-xs bg-muted p-2 rounded overflow-auto">
                  {this.state.error.toString()}
                  {this.state.error.stack && (
                    <>
                      {"\n\n"}
                      {this.state.error.stack}
                    </>
                  )}
                </pre>
              </details>
            )}
            <Button
              variant="outline"
              size="sm"
              onClick={this.handleReset}
              className="mt-2"
            >
              Try Again
            </Button>
          </AlertDescription>
        </Alert>
      );
    }

    return this.props.children;
  }
}

// Higher-order component for functional components
export function withEmailErrorBoundary<P extends object>(
  Component: React.ComponentType<P>,
  fallback?: ReactNode,
  onError?: (error: Error, errorInfo: ErrorInfo) => void
) {
  return (props: P) => (
    <EmailErrorBoundary fallback={fallback} onError={onError}>
      <Component {...props} />
    </EmailErrorBoundary>
  );
}