## Shell 不是公式

```bash
ubuntu@EXP:~$ gcc main.c -o main
ubuntu@EXP:~$ ./main
printf '%s\n' "$HOME" "${USER}" "$(pwd)" "$$"
awk '{print $1, $2}' input.txt
printf '%s\n' '$x$' '$$x^2$$' '<em>$literal</em>'
```

行内命令 `echo "$HOME" "$USER"`，数学语法示例 `$x^2$` 和 `$$x^2$$` 也应保持原样。

## 正文仍支持公式

行内公式 $a^2 + b^2 = c^2$ 与 $x_1 + x_2$。

独立公式 $$\sum_{i=1}^{n} i = \frac{n(n+1)}{2}$$。

```katex
\int_0^1 x^2\,dx = \frac{1}{3}
```
