import { $generateHtmlFromNodes, $generateNodesFromDOM } from '@lexical/html'
import { AutoLinkNode, LinkNode, TOGGLE_LINK_COMMAND } from '@lexical/link'
import {
  INSERT_ORDERED_LIST_COMMAND,
  INSERT_UNORDERED_LIST_COMMAND,
  ListItemNode,
  ListNode,
  REMOVE_LIST_COMMAND,
} from '@lexical/list'
import { LexicalComposer } from '@lexical/react/LexicalComposer'
import { ContentEditable } from '@lexical/react/LexicalContentEditable'
import { HistoryPlugin } from '@lexical/react/LexicalHistoryPlugin'
import { LexicalErrorBoundary } from '@lexical/react/LexicalErrorBoundary'
import { LinkPlugin } from '@lexical/react/LexicalLinkPlugin'
import { ListPlugin } from '@lexical/react/LexicalListPlugin'
import { OnChangePlugin } from '@lexical/react/LexicalOnChangePlugin'
import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext'
import { RichTextPlugin } from '@lexical/react/LexicalRichTextPlugin'
import { $setBlocksType } from '@lexical/selection'
import { $createHeadingNode, $createQuoteNode, HeadingNode, QuoteNode } from '@lexical/rich-text'
import {
  $createParagraphNode,
  $getRoot,
  $getSelection,
  $insertNodes,
  $isRangeSelection,
  FORMAT_TEXT_COMMAND,
  type EditorState,
  type LexicalEditor,
} from 'lexical'
import { useRef } from 'react'
import { Box, Button, MenuItem, Stack, TextField } from '../../ui'

interface RichTextEditorProps {
  value: string
  onChange: (html: string) => void
}

export function RichTextEditor({ value, onChange }: RichTextEditorProps) {
  const initialValue = useRef(value)

  return (
    <LexicalComposer
      initialConfig={{
        namespace: 'MorfiraRichText',
        nodes: [HeadingNode, QuoteNode, ListNode, ListItemNode, LinkNode, AutoLinkNode],
        onError(error) {
          throw error
        },
        editorState: (editor) => importHtml(editor, initialValue.current),
        theme: {
          heading: { h2: 'morfira-editor-h2', h3: 'morfira-editor-h3', h4: 'morfira-editor-h4' },
          link: 'morfira-editor-link',
          list: { listitem: 'morfira-editor-listitem', ol: 'morfira-editor-list', ul: 'morfira-editor-list' },
          quote: 'morfira-editor-quote',
          text: { bold: 'morfira-editor-bold', italic: 'morfira-editor-italic', strikethrough: 'morfira-editor-strike', underline: 'morfira-editor-underline' },
        },
      }}
    >
      <Box sx={{ border: 1, borderColor: 'divider', borderRadius: 1, overflow: 'hidden' }}>
        <Toolbar />
        <Box sx={{ position: 'relative' }}>
          <RichTextPlugin
            contentEditable={(
              <ContentEditable
                aria-label="Rich text content"
                style={{ minHeight: 180, outline: 'none', padding: '16px' }}
              />
            )}
            placeholder={<Box sx={{ color: 'text.disabled', left: 16, pointerEvents: 'none', position: 'absolute', top: 16 }}>Start writing useful content…</Box>}
            ErrorBoundary={LexicalErrorBoundary}
          />
        </Box>
      </Box>
      <HistoryPlugin />
      <ListPlugin />
      <LinkPlugin />
      <OnChangePlugin
        ignoreSelectionChange
        onChange={(editorState, editor) => emitHtml(editorState, editor, onChange)}
      />
    </LexicalComposer>
  )
}

function Toolbar() {
  const [editor] = useLexicalComposerContext()

  function setBlock(type: string) {
    if (type === 'bullet') editor.dispatchCommand(INSERT_UNORDERED_LIST_COMMAND, undefined)
    else if (type === 'number') editor.dispatchCommand(INSERT_ORDERED_LIST_COMMAND, undefined)
    else if (type === 'paragraph') {
      editor.dispatchCommand(REMOVE_LIST_COMMAND, undefined)
      editor.update(() => $setBlocksType($getSelection(), () => $createParagraphNode()))
    } else {
      editor.dispatchCommand(REMOVE_LIST_COMMAND, undefined)
      editor.update(() => {
        const selection = $getSelection()
        if (!$isRangeSelection(selection)) return
        $setBlocksType(selection, () => type === 'quote' ? $createQuoteNode() : $createHeadingNode(type as 'h2' | 'h3' | 'h4'))
      })
    }
  }

  function editLink() {
    const url = window.prompt('Link URL. Leave empty to remove the link.')
    if (url === null) return
    editor.dispatchCommand(TOGGLE_LINK_COMMAND, url.trim() || null)
  }

  return (
    <Stack direction="row" spacing={0.75} sx={{ alignItems: 'center', bgcolor: 'grey.50', borderBottom: 1, borderColor: 'divider', flexWrap: 'wrap', gap: 0.75, p: 1 }}>
      <TextField select size="small" defaultValue="paragraph" aria-label="Block style" sx={{ minWidth: 130 }} onChange={(event) => setBlock(event.target.value)}>
        <MenuItem value="paragraph">Paragraph</MenuItem>
        <MenuItem value="h2">Heading 2</MenuItem>
        <MenuItem value="h3">Heading 3</MenuItem>
        <MenuItem value="h4">Heading 4</MenuItem>
        <MenuItem value="quote">Quote</MenuItem>
        <MenuItem value="bullet">Bullet list</MenuItem>
        <MenuItem value="number">Numbered list</MenuItem>
      </TextField>
      <Button size="small" onClick={() => editor.dispatchCommand(FORMAT_TEXT_COMMAND, 'bold')}><strong>B</strong></Button>
      <Button size="small" onClick={() => editor.dispatchCommand(FORMAT_TEXT_COMMAND, 'italic')}><em>I</em></Button>
      <Button size="small" onClick={() => editor.dispatchCommand(FORMAT_TEXT_COMMAND, 'underline')}><u>U</u></Button>
      <Button size="small" onClick={() => editor.dispatchCommand(FORMAT_TEXT_COMMAND, 'strikethrough')}><s>S</s></Button>
      <Button size="small" onClick={editLink}>Link</Button>
    </Stack>
  )
}

function importHtml(editor: LexicalEditor, html: string) {
  const parser = new DOMParser()
  const document = parser.parseFromString(html || '<p></p>', 'text/html')
  const nodes = $generateNodesFromDOM(editor, document)
  const root = $getRoot()
  root.clear()
  if (nodes.length === 0) root.append($createParagraphNode())
  else $insertNodes(nodes)
}

function emitHtml(editorState: EditorState, editor: LexicalEditor, onChange: (html: string) => void) {
  editorState.read(() => onChange($generateHtmlFromNodes(editor)))
}
