import sys
from pathlib import Path

# Permite `from tennis.x import y` sem instalar o pacote — tools/ é a raiz
# do pacote "tennis" (tools/tennis/__init__.py).
TOOLS_DIR = Path(__file__).resolve().parents[2] / "tools"
if str(TOOLS_DIR) not in sys.path:
    sys.path.insert(0, str(TOOLS_DIR))
