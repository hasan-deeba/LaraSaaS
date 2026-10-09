"use client";

import { useState, type ReactNode } from "react";
import { Check, Copy, Terminal as TerminalIcon } from "lucide-react";

/* ------------------------------------------------------------------ */
/*  Tiny regex highlighter — zero dependencies, tuned for PHP/TS/bash  */
/* ------------------------------------------------------------------ */

const MASTER = new RegExp(
  [
    // 1: comments
    "(\\/\\/[^\\n]*|\\/\\*[\\s\\S]*?\\*\\/|#[^\\n]*)",
    // 2: strings
    "('(?:[^'\\\\]|\\\\.)*'|\"(?:[^\"\\\\]|\\\\.)*\"|`(?:[^`\\\\]|\\\\.)*`)",
    // 3: numbers
    "(\\b\\d[\\d_.,]*\\b)",
    // 4: php variables
    "(\\$[A-Za-z_]\\w*)",
    // 5: keywords
    "\\b(use|namespace|return|yield|function|fn|class|extends|implements|new|public|protected|private|static|readonly|final|abstract|import|from|export|default|const|let|var|if|else|elseif|foreach|as|while|try|catch|throw|string|int|bool|float|array|object|mixed|void|null|true|false|echo|match|enum|interface|self|parent|resolvePageComponent|composer|require|php|npx|npm|artisan)\\b",
    // 6: class/type references
    "\\b([A-Z][A-Za-z0-9_]*(?:\\\\[A-Z][A-Za-z0-9_]*)*)\\b",
    // 7: function calls
    "([a-zA-Z_]\\w*)(?=\\()",
    // 8: array keys / props before =>
    "(\\w+)(?=\\s*=>)",
  ].join("|"),
  "gm",
);

const CLASSES = ["tk-com", "tk-str", "tk-num", "tk-var", "tk-kw", "tk-attr", "tk-fn", "tk-attr"];

export function highlight(code: string): ReactNode[] {
  const out: ReactNode[] = [];
  let last = 0;
  let key = 0;
  let match: RegExpExecArray | null;

  MASTER.lastIndex = 0;

  while ((match = MASTER.exec(code)) !== null) {
    if (match.index > last) {
      out.push(<span key={key++} className="tk-pn">{code.slice(last, match.index)}</span>);
    }

    const group = match.findIndex((g, i) => i > 0 && g !== undefined);

    out.push(
      <span key={key++} className={CLASSES[group - 1] ?? "tk-pn"}>
        {match[0]}
      </span>,
    );

    last = match.index + match[0].length;
    if (match[0].length === 0) MASTER.lastIndex++;
  }

  if (last < code.length) {
    out.push(<span key={key++} className="tk-pn">{code.slice(last)}</span>);
  }

  return out;
}

/* ------------------------------------------------------------------ */
/*  Copy button                                                        */
/* ------------------------------------------------------------------ */

export function CopyButton({ text, className = "" }: { text: string; className?: string }) {
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(text);
    } catch {
      /* clipboard unavailable */
    }
    setCopied(true);
    setTimeout(() => setCopied(false), 1600);
  };

  return (
    <button
      onClick={copy}
      aria-label="Copy"
      className={`inline-flex items-center justify-center rounded-lg border border-white/10 bg-white/5 p-1.5 text-zinc-400 transition hover:text-white ${className}`}
    >
      {copied ? <Check size={14} className="text-emerald-400" /> : <Copy size={14} />}
    </button>
  );
}

/* ------------------------------------------------------------------ */
/*  CodeBlock                                                          */
/* ------------------------------------------------------------------ */

export function CodeBlock({
  code,
  title,
  lang = "php",
  className = "",
  maxHeight,
}: {
  code: string;
  title?: string;
  lang?: string;
  className?: string;
  maxHeight?: number;
}) {
  return (
    <figure className={`code-shell ${className}`} data-lang={lang}>
      <figcaption className="flex items-center justify-between gap-3 border-b border-white/[0.06] px-4 py-2.5">
        <span className="flex items-center gap-2">
          <span className="flex gap-1.5">
            <i className="h-2.5 w-2.5 rounded-full bg-[#ff5f57]" />
            <i className="h-2.5 w-2.5 rounded-full bg-[#febc2e]" />
            <i className="h-2.5 w-2.5 rounded-full bg-[#28c840]" />
          </span>
          <span className="ms-2 font-mono text-[11px] text-zinc-500">{title ?? lang}</span>
        </span>
        <CopyButton text={code} />
      </figcaption>
      <pre className="code-body overflow-auto p-4" style={maxHeight ? { maxHeight } : undefined} dir="ltr">
        <code>{highlight(code)}</code>
      </pre>
    </figure>
  );
}

/* ------------------------------------------------------------------ */
/*  Terminal                                                           */
/* ------------------------------------------------------------------ */

export function Terminal({
  lines,
  title = "terminal — zsh",
  className = "",
  children,
}: {
  lines?: string[];
  title?: string;
  className?: string;
  children?: ReactNode;
}) {
  return (
    <div className={`code-shell ${className}`} dir="ltr">
      <div className="flex items-center justify-between border-b border-white/[0.06] px-4 py-2.5">
        <span className="flex items-center gap-2 text-zinc-500">
          <TerminalIcon size={13} />
          <span className="font-mono text-[11px]">{title}</span>
        </span>
        {lines && <CopyButton text={lines.join("\n")} />}
      </div>
      <div className="code-body p-4">
        {children ??
          lines?.map((line) => (
            <div key={line} className="whitespace-pre-wrap">
              <span className="text-accent">$ </span>
              {highlight(line)}
            </div>
          ))}
      </div>
    </div>
  );
}
