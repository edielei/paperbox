<?php
// markdown.php — 简易 Markdown 解析器

class SimpleMD {

    public function text($text) {
        $text = trim($text);
        if ($text === '') return '';

        $lines = explode("\n", $text);
        $html = [];
        $i = 0;
        $n = count($lines);

        while ($i < $n) {
            $line = $lines[$i];
            $trimmed = trim($line);

            if ($trimmed === '') { $i++; continue; }

            // 代码块 ```language
            if (preg_match('/^```(\w*)/', $trimmed, $m)) {
                $lang = $m[1];
                $code = [];
                $i++;
                while ($i < $n && !preg_match('/^```\s*$/', trim($lines[$i]))) {
                    $code[] = $lines[$i];
                    $i++;
                }
                $i++;
                $cls = $lang ? ' class="language-' . e($lang) . '"' : '';
                $html[] = '<pre><code' . $cls . '>' . htmlspecialchars(implode("\n", $code), ENT_QUOTES, 'UTF-8') . '</code></pre>';
                continue;
            }

            // 标题 # ## ###
            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $m)) {
                $level = strlen($m[1]);
                $html[] = "<h$level>" . $this->inline($m[2]) . "</h$level>";
                $i++;
                continue;
            }

            // 警告框 > [!NOTE] / > [!TIP] / > [!IMPORTANT] / > [!WARNING] / > [!CAUTION]
            if (preg_match('/^>\s*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*$/i', $trimmed, $m)) {
                $type = strtoupper($m[1]);
                $body = [];
                $i++;
                while ($i < $n && preg_match('/^>\s?(.*)/', $lines[$i], $m2)) {
                    $body[] = $m2[1];
                    $i++;
                }
                $typeMap = [
                    'NOTE'      => ['cls' => 'note',      'title' => '注意',  'icon' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8" fill="currentColor" opacity="0.15"/><path d="M8 4a1 1 0 0 1 1 1v4a1 1 0 1 1-2 0V5a1 1 0 0 1 1-1zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" fill="currentColor"/></svg>'],
                    'TIP'       => ['cls' => 'tip',       'title' => '提示',  'icon' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8" fill="currentColor" opacity="0.15"/><path d="M8 2a4.5 4.5 0 0 0-2.8 8.05V11a.5.5 0 0 0 .5.5h4.6a.5.5 0 0 0 .5-.5v-.95A4.5 4.5 0 0 0 8 2zM6.5 12.5a.5.5 0 0 0 .5.5h2a.5.5 0 0 0 0-1H7a.5.5 0 0 0-.5.5z" fill="currentColor"/></svg>'],
                    'IMPORTANT' => ['cls' => 'important', 'title' => '重要',  'icon' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8" fill="currentColor" opacity="0.15"/><path d="M8 3a1 1 0 0 1 1 1v4a1 1 0 1 1-2 0V4a1 1 0 0 1 1-1zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" fill="currentColor"/></svg>'],
                    'WARNING'   => ['cls' => 'warning',   'title' => '警告',  'icon' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8" fill="currentColor" opacity="0.15"/><path d="M8 3.5a.6.6 0 0 1 .54.33l4.5 8A.6.6 0 0 1 12.5 13h-9a.6.6 0 0 1-.54-.87l4.5-8A.6.6 0 0 1 8 3.5zM7.5 7v2.5a.5.5 0 0 0 1 0V7a.5.5 0 0 0-1 0zM8 11.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5z" fill="currentColor"/></svg>'],
                    'CAUTION'   => ['cls' => 'caution',   'title' => '危险',  'icon' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8" fill="currentColor" opacity="0.15"/><path d="M4.5 4.5l7 7M11.5 4.5l-7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>'],
                ];
                $info = $typeMap[$type];
                $html[] = '<div class="md-alert md-alert-' . $info['cls'] . '">';
                $html[] = '<div class="md-alert-title">' . $info['icon'] . '<span>' . $info['title'] . '</span></div>';
                $html[] = '<div class="md-alert-body">' . $this->text(implode("\n", $body)) . '</div>';
                $html[] = '</div>';
                continue;
            }

            // 引用 >
            if (preg_match('/^>\s?(.*)/', $line, $m)) {
                $quote = [];
                while ($i < $n && preg_match('/^>\s?(.*)/', $lines[$i], $m2)) {
                    $quote[] = $m2[1];
                    $i++;
                }
                $html[] = '<blockquote>' . $this->text(implode("\n", $quote)) . '</blockquote>';
                continue;
            }

            // 列表（支持嵌套，无序/有序混合）
            if (preg_match('/^([-*+]|\d+\.)\s+/', $trimmed)) {
                $baseIndent = $this->get_indent($line);
                $items = $this->parse_list($lines, $i, $n, $baseIndent);
                $html[] = $this->render_list($items);
                continue;
            }

            // 分隔线 --- ***
            if (preg_match('/^([-*_])\1{2,}$/', $trimmed)) {
                $html[] = '<hr>';
                $i++;
                continue;
            }

            // 表格 | 列1 | 列2 |
            //      | --- | --- |
            //      | 内容 | 内容 |
            if ($i + 1 < $n && strpos($trimmed, '|') !== false
                && preg_match('/^\s*\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)+\|?\s*$/', trim($lines[$i + 1]))) {
                $header = $this->parse_table_row($trimmed);
                $i++;
                $aligns = $this->parse_table_align(trim($lines[$i]), count($header));
                $i++;
                $rows = [];
                while ($i < $n && trim($lines[$i]) !== '' && strpos($lines[$i], '|') !== false) {
                    $rows[] = $this->parse_table_row($lines[$i]);
                    $i++;
                }
                $html[] = '<div class="md-table-wrap"><table>';
                $html[] = '<thead><tr>';
                foreach ($header as $idx => $cell) {
                    $al = isset($aligns[$idx]) ? ' style="text-align:' . $aligns[$idx] . '"' : '';
                    $html[] = '<th' . $al . '>' . $this->inline($cell) . '</th>';
                }
                $html[] = '</tr></thead>';
                if ($rows) {
                    $html[] = '<tbody>';
                    foreach ($rows as $row) {
                        $html[] = '<tr>';
                        foreach ($row as $idx => $cell) {
                            $al = isset($aligns[$idx]) ? ' style="text-align:' . $aligns[$idx] . '"' : '';
                            $html[] = '<td' . $al . '>' . $this->inline($cell) . '</td>';
                        }
                        $html[] = '</tr>';
                    }
                    $html[] = '</tbody>';
                }
                $html[] = '</table></div>';
                continue;
            }

            // 普通段落
            $para = [$trimmed];
            $i++;
            while ($i < $n && trim($lines[$i]) !== '' && !preg_match('/^[>#*-]|^\d+\.|^```/', trim($lines[$i]))) {
                $para[] = trim($lines[$i]);
                $i++;
            }
            $html[] = '<p>' . $this->inline(implode(' ', $para)) . '</p>';
        }

        return implode("\n", $html);
    }

    private function inline($text) {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // 行内代码 `code`
        $text = preg_replace_callback('/`([^`]+)`/', function($m) {
            return '<code>' . $m[1] . '</code>';
        }, $text);

        // 图片 ![alt](url) — URL 安全校验
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function($m) {
            $url = $this->safe_url($m[2]);
            if ($url === '') return e($m[0]);
            return '<img src="' . e($url) . '" alt="' . e($m[1]) . '">';
        }, $text);

        // 链接 [text](url) — URL 安全校验
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function($m) {
            $url = $this->safe_url($m[2]);
            if ($url === '') return e($m[0]);
            return '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
        }, $text);

        // 自动链接（已限定 http/https，排除已在标签属性中的 URL）
        $text = preg_replace('/(?<![="\w])(https?:\/\/[^\s<]+)/', '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', $text);

        // 粗体 **text** / __text__
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);

        // 斜体 *text* / _text_
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_(.+?)_/', '<em>$1</em>', $text);

        // 删除线 ~~text~~
        $text = preg_replace('/~~(.+?)~~/', '<del>$1</del>', $text);

        // 下标 ~text~（单个波浪线，在删除线之后处理）
        $text = preg_replace('/~([^~]+?)~/', '<sub>$1</sub>', $text);

        // 高亮 ==text==
        $text = preg_replace('/==(.+?)==/', '<mark>$1</mark>', $text);

        // 下划线 ++text++
        $text = preg_replace('/\+\+(.+?)\+\+/', '<u>$1</u>', $text);

        // 上标 ^text^
        $text = preg_replace('/\^(.+?)\^/', '<sup>$1</sup>', $text);

        return $text;
    }

    // 解析表格行，返回单元格数组
    private function parse_table_row($line) {
        $line = trim($line);
        $line = preg_replace('/^\|/', '', $line);
        $line = preg_replace('/\|$/', '', $line);
        return array_map('trim', explode('|', $line));
    }

    // 解析分隔行，返回每列对齐方式（left/center/right）
    private function parse_table_align($line, $count) {
        $line = preg_replace('/^\|/', '', $line);
        $line = preg_replace('/\|$/', '', $line);
        $parts = explode('|', $line);
        $aligns = [];
        foreach ($parts as $part) {
            $part = trim($part);
            $left = strpos($part, ':') === 0;
            $right = substr($part, -1) === ':';
            if ($left && $right) $aligns[] = 'center';
            elseif ($right) $aligns[] = 'right';
            else $aligns[] = 'left';
        }
        while (count($aligns) < $count) $aligns[] = 'left';
        return $aligns;
    }

    // URL 协议白名单：仅允许 http/https/相对路径/mailto/tel/锚点
    private function safe_url($url) {
        $url = trim($url);
        if ($url === '') return '';
        // 明确允许的协议
        if (preg_match('/^(https?:|mailto:|tel:)/i', $url)) return $url;
        // 相对路径、绝对路径、锚点
        if ($url[0] === '/' || $url[0] === '#' || strpos($url, '?') === 0) return $url;
        // 不含冒号的视为相对路径
        if (strpos($url, ':') === false) return $url;
        // 其他协议（javascript:、data: 等）拒绝
        return '';
    }

    // 获取行首缩进空格数（tab 按 4 空格算）
    private function get_indent($line) {
        $line = str_replace("\t", '    ', $line);
        preg_match('/^ */', $line, $m);
        return strlen($m[0]);
    }

    // 递归解析列表，返回嵌套数组
    private function parse_list($lines, &$i, $n, $baseIndent) {
        $items = [];
        while ($i < $n) {
            $line = $lines[$i];
            $trimmed = trim($line);
            if ($trimmed === '') {
                // 空行：探测下一行是否仍属本列表
                $next = $i + 1;
                while ($next < $n && trim($lines[$next]) === '') $next++;
                if ($next < $n && $this->get_indent($lines[$next]) >= $baseIndent
                    && preg_match('/^([-*+]|\d+\.)\s+/', trim($lines[$next]))) {
                    $i = $next;
                    continue;
                }
                break;
            }
            $indent = $this->get_indent($line);
            if ($indent < $baseIndent) break;
            if ($indent > $baseIndent) {
                // 嵌套子列表，递归
                if (!empty($items)) {
                    $sub = $this->parse_list($lines, $i, $n, $indent);
                    $items[count($items) - 1]['children'] = array_merge(
                        $items[count($items) - 1]['children'], $sub
                    );
                } else {
                    $i++;
                }
                continue;
            }
            // 同级列表项
            if (preg_match('/^[-*+]\s+(.+)/', $trimmed, $m)) {
                $items[] = ['type' => 'ul', 'text' => $m[1], 'children' => []];
                $i++;
            } elseif (preg_match('/^\d+\.\s+(.+)/', $trimmed, $m)) {
                $items[] = ['type' => 'ol', 'text' => $m[1], 'children' => []];
                $i++;
            } else {
                break;
            }
        }
        return $items;
    }

    // 递归渲染列表为 HTML
    private function render_list($items) {
        if (empty($items)) return '';
        $type = $items[0]['type'];
        $tag = ($type === 'ol') ? 'ol' : 'ul';
        $html = "<$tag>";
        foreach ($items as $item) {
            $html .= '<li>' . $this->inline($item['text']);
            if (!empty($item['children'])) {
                $html .= $this->render_list($item['children']);
            }
            $html .= '</li>';
        }
        $html .= "</$tag>";
        return $html;
    }
}
