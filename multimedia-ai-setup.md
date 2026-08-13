# Multimedia AI Setup Guide — 2D / Video / 3D

**版本**: v0.1
**適用對象**: Altodock Digital，三人 part-time team
**配套文件**: `document-copilot-implementation.md`

---

## 0. 先講結論

多媒體生成同文件分析唔喺同一個投資量級。文件分析可以用 API 做到有商業價值嘅產品；多媒體生成如果自建，硬件係前置成本，而且**技術迭代快到你嘅硬件半年就落後**。

**建議投入次序**：

| 範疇 | 自建可行性 | 建議 |
|---|---|---|
| **2D 圖像 / 遊戲素材** | 高 —— 單張 24GB GPU 夠用 | ✅ 值得自建，LoRA 可做風格一致性，呢個係真正嘅差異化 |
| **3D model** | 中 —— 6–16GB VRAM 已可推論 | ⚠️ 生成易，但 game-ready 後製先係真功夫 |
| **影片** | 低 —— 高質輸出需 40–80GB VRAM | ❌ 初期用 API，唔好自建 |

呢份文件按呢個次序寫。如果時間有限，**只讀第 2 節（2D）**就夠開始。

---

## 1. 共用基礎設施

三個範疇共用同一套底層，先起呢層。

### 1.1 硬件

| 配置 | 用途 | 成本參考 |
|---|---|---|
| **RTX 4090 / 5090（24–32GB）** | 2D 生成 + LoRA 訓練、3D 生成、影片實驗 | 買機約 US$2,000–2,800 |
| **雲端 A10G / 4090（按小時）** | 起步階段、間歇性用量 | US$0.4–0.8/小時 |
| **A100 / H100（40–80GB）** | 影片高質輸出、大模型 LoRA 訓練 | US$2–4/小時，唔建議買 |

**起步建議**：租 RunPod / Vast.ai / AutoDL 嘅 4090，用夠 200 小時（約 US$150）先計數自己買抵唔抵。香港電費同散熱亦要計入自建成本。

### 1.2 軟件底座

```bash
# 環境
Ubuntu 22.04 / 24.04
NVIDIA Driver 550+
CUDA 12.4+
Python 3.10 或 3.11（唔好用 3.12，好多節點未跟上）

# 核心
PyTorch 2.4+ (cu124)
ComfyUI              # 工作流編排，業界標準
ComfyUI-Manager      # 節點管理，必裝
```

**點解一定要 ComfyUI**：2D、3D、影片三個範疇嘅開源模型幾乎全部有 ComfyUI 節點包。用同一個介面編排，唔使為每個模型寫獨立 Python script。而且 workflow 可以匯出成 JSON，之後用 ComfyUI 嘅 API mode 由 Laravel 呼叫，直接接入你哋現有系統。

```bash
git clone https://github.com/comfyanonymous/ComfyUI
cd ComfyUI
pip install -r requirements.txt

# Manager
cd custom_nodes
git clone https://github.com/ltdrdata/ComfyUI-Manager

# 啟動（API mode，俾 Laravel 呼叫）
python main.py --listen 0.0.0.0 --port 8188
```

### 1.3 接入現有 stack

```
Vue 3 前端
    ↓ 提交生成任務
Laravel 11 API
    ↓ 推入 Redis Queue
Laravel Queue Worker
    ↓ HTTP POST /prompt (ComfyUI API)
ComfyUI (GPU 機)
    ↓ webhook / polling
儲存輸出 → S3 → 回寫 DB → SSE 通知前端
```

ComfyUI 嘅 `/prompt` endpoint 收 workflow JSON，`/history/{prompt_id}` 查結果。Laravel 只需要一個 HTTP client + queue worker，唔使寫 Python。

**關鍵設計**：workflow JSON 存喺 DB，參數（prompt、seed、LoRA 權重）由 Laravel 動態注入。咁樣改工作流唔使改 code。

---

## 2. 2D 圖像生成 — 優先做呢個

呢個係唯一喺你哋現階段規模下，自建有明顯商業價值嘅範疇。原因：**LoRA 微調可以做到風格一致性，而風格一致性係 API 服務做唔到嘅嘢**。客戶要一整套視覺風格統一嘅素材，通用 API 做唔到，你 train 一個 LoRA 就做到。

### 2.1 Base model 選型

| 模型 | 授權 | VRAM | 適用 |
|---|---|---|---|
| **FLUX.1 [schnell]** | Apache 2.0 | 12–16GB | 商用最安全，速度快 |
| **FLUX.1 [dev]** | 非商用授權 ⚠️ | 16–24GB | 質素高但**商用要買授權**，注意 |
| **Stable Diffusion 3.5 Large** | Stability Community License | 16–24GB | 生態最大，LoRA / ControlNet 最齊 |
| **SDXL** | CreativeML OpenRAIL-M | 8–12GB | 舊但穩定，工具鏈最成熟 |

**建議**：商用產品用 **FLUX.1 schnell**（Apache 2.0 冇後患）或 **SD3.5**。FLUX dev 好用但授權係陷阱，好多人踩過 —— 商業使用要向 Black Forest Labs 買 licence。

### 2.2 安裝

```bash
cd ComfyUI/models/checkpoints
# FLUX schnell
wget https://huggingface.co/black-forest-labs/FLUX.1-schnell/resolve/main/flux1-schnell.safetensors

cd ../clip
wget .../t5xxl_fp8_e4m3fn.safetensors
wget .../clip_l.safetensors

cd ../vae
wget .../ae.safetensors
```

VRAM 緊張就用 FP8 版本，24GB 卡好鬆動。

### 2.3 LoRA 訓練（風格一致性）

呢個係核心價值所在。

**工具**：**kohya_ss**（sd-scripts）—— 2D LoRA 訓練嘅事實標準；或 **ai-toolkit**（FLUX 專用，較新較簡單）。

```bash
git clone https://github.com/kohya-ss/sd-scripts
cd sd-scripts
pip install -r requirements.txt
accelerate config
```

**訓練資料需求**（比 LLM LoRA 少好多）：

| 目標 | 圖片數量 | 訓練時間（4090） |
|---|---|---|
| 單一角色 | 15–30 張 | 20–40 分鐘 |
| 統一畫風 | 30–80 張 | 1–2 小時 |
| 完整素材集風格 | 100–200 張 | 3–5 小時 |

**資料準備要點**：

1. 解析度統一（1024×1024，或用 bucketing 處理不同比例）
2. 每張圖配 caption 檔（`img001.png` + `img001.txt`）
3. Caption 用 WD14 Tagger 或 BLIP 自動生成，再人手修
4. **關鍵**：加一個獨特 trigger word（例如 `altodock_style`），caption 入面每張都要有。唔好用常見字做 trigger

**核心參數起點**：

```
network_module   = networks.lora
network_dim      = 32          # 風格用 16–32，角色用 32–64
network_alpha    = 16          # 通常係 dim 一半
learning_rate    = 1e-4
text_encoder_lr  = 5e-5
unet_lr          = 1e-4
lr_scheduler     = cosine_with_restarts
train_batch_size = 1
max_train_epochs = 10
mixed_precision  = bf16
gradient_checkpointing = true   # VRAM 緊張時開
save_every_n_epochs = 1         # 存多個 checkpoint，之後揀最好嗰個
```

**常見問題**：

- **過擬合**（出圖全部似訓練圖）→ 減 epoch、降 dim、加訓練圖多樣性
- **學唔到風格** → 加 epoch、升 learning rate、檢查 caption 有冇 trigger word
- **崩壞 / 出怪圖** → learning rate 太高，降到 5e-5 試

**必做**：每個 epoch 存 checkpoint，用同一組 prompt + seed 生成對比圖，用眼揀最好嗰個。唔好靠 loss 值判斷 —— 圖像生成嘅 loss 同主觀質素關聯好弱。

### 2.4 遊戲素材專用工作流

2D 遊戲素材唔係「生成一張靚圖」咁簡單，有具體技術要求：

| 需求 | 解決方案 |
|---|---|
| 透明背景 | 生成後用 `rembg` 或 SAM 去背，ComfyUI 有節點 |
| 同一角色多角度 | ControlNet (OpenPose) + 角色 LoRA + 固定 seed |
| Tileable 貼圖 | 用 seamless texture 專用 LoRA / `--tiling` 選項 |
| Sprite sheet | 生成單幀後用 Python (Pillow) 拼合 |
| 尺寸規格 | 生成高解析後 downscale，唔好直接生成細圖 |
| 色板一致 | 後製做 palette quantization，唔好指望模型控制 |

**Pixel art 特別注意**：diffusion 模型生成嘅「像素風」通常唔係真 pixel-perfect，需要後製 downscale + palette 量化。有專門 pixel art LoRA，但仍需後製。

### 2.5 2D 落地里程碑

- [ ] ComfyUI + FLUX schnell 跑通第一張圖
- [ ] Laravel 經 API 呼叫 ComfyUI 成功
- [ ] 收集 30 張風格參考圖，訓練第一個 LoRA
- [ ] 對比 LoRA 前後輸出，確認風格可控
- [ ] 建立去背 + 尺寸規格化後製流程
- [ ] 做一個內部 demo：輸入描述 → 出一組風格統一嘅素材

---

## 3. 影片生成 — 建議用 API，唔好自建

### 3.1 現實嘅 VRAM 需求

呢個係最多人低估嘅地方。<cite index="15-1">開源影片模型如 LTX-2.3 同 WAN 2.2 嘅全質素輸出需要 80GB+ VRAM</cite>。量化之後可以降低，但質素有損失：

| 模型 | 授權 | 全精度 VRAM | 量化後（FP8/GGUF） | 備註 |
|---|---|---|---|---|
| **Wan 2.2** | Apache 2.0 ✅ | 80GB+ | 14–18GB (14B FP8) | 商用授權最乾淨 |
| **Wan 2.1/2.2 (1.3B)** | Apache 2.0 ✅ | — | 4–8GB | 480p，適合快速試驗 |
| **LTX-2.3** | LTX License ⚠️ | 80GB+ | 32GB（蒸餾版 FP8） | 速度最快，原生音訊 |
| **HunyuanVideo 1.5** | Tencent Community ⚠️ | 60–80GB | ~8–16GB (FP8+tiling) | 質素高，授權要細睇 |
| **CogVideoX-5B** | Apache 2.0 ✅ | — | ~16GB | 文字跟隨準確 |

<cite index="16-1">喺 24GB 嘅 RTX 4090 上，基本上所有模型嘅 FP8 量化版本都塞得落</cite>，但輸出通常係 480–720p、5 秒左右。

### 3.2 點解建議用 API

計一條數：

- 自建：4090 機約 US$2,500 + 電費 + 維運時間，輸出 720p / 5 秒
- API：<cite index="10-1">Runway Gen-3 Alpha Turbo 約 US$0.25 一段 5 秒片，Gen-3 Alpha 約 US$0.50</cite>

即係話買機嗰筆錢，用 API 可以生 5,000–10,000 段片。除非你月產量超過幾百條片，否則自建唔抵。而且模型每三個月換代一次，API 自動升級，自建就要重新配置。

**建議 API**：Runway、Kling、Luma、通義萬相（阿里，如果客戶接受中國區服務）。

### 3.3 幾時先值得自建影片

同時滿足：
1. 月產量 > 500 條片
2. 內容涉及唔可以上傳第三方嘅素材（客戶 IP、未公開產品）
3. 需要用自家素材 fine-tune 影片模型（呢個 API 做唔到）

呢三個條件喺你哋現階段都未成立。

### 3.4 如果真係要試（低成本路徑）

租雲端 4090 幾個鐘，跑 **Wan 2.2 14B FP8**（Apache 2.0 授權最安全），ComfyUI 有現成 workflow。純粹用嚟理解技術限制，唔好當生產環境。

```bash
# ComfyUI-Manager 入面搜 "WanVideoWrapper" 安裝
# 模型放 ComfyUI/models/diffusion_models/
```

---

## 4. 3D 模型生成

### 4.1 模型選型

| 模型 | 授權 | VRAM | 特點 |
|---|---|---|---|
| **Hunyuan3D 2.1** | Tencent Community ⚠️ | <cite index="23-1">形狀 6GB，形狀+貼圖 12–16GB</cite> | <cite index="20-1">首個完整開源嘅 PBR 貼圖 3D 生成方案，開源咗資料處理同訓練 pipeline</cite> |
| **TRELLIS** (Microsoft) | MIT ✅ | 12–16GB | <cite index="24-1">單張高質參考圖嘅 image-to-3D 重建最乾淨，輸出可選 mesh / Gaussian splat / radiance field</cite> |
| **Stable Fast 3D** | Stability licence | 8GB | 速度最快（<1 秒），質素較低 |
| **InstantMesh** | Apache 2.0 ✅ | 16GB | 較舊但穩定 |

**建議**：商用先睇 **TRELLIS**（MIT 授權最乾淨）。Hunyuan3D 2.1 質素同 PBR 材質更好，但係 Tencent Community License，商用前要逐條睇清楚。

<cite index="23-1">Hunyuan3D 用兩階段 pipeline：Hunyuan3D-DiT 生成幾何，Hunyuan3D-Paint 做貼圖合成，完整資產約 10–25 秒</cite>。

### 4.2 安裝（Hunyuan3D 為例）

```bash
# ComfyUI 路徑
cd ComfyUI/custom_nodes
git clone https://github.com/kijai/ComfyUI-Hunyuan3DWrapper
# 或 ComfyUI-3D-Pack（功能更全但依賴較重）

cd ComfyUI-Hunyuan3DWrapper
pip install -r requirements.txt

# 模型會自動由 HuggingFace 下載，或手動放
# ComfyUI/models/diffusion_models/hunyuan3d/
```

亦有 **Blender addon**，如果你哋有人熟 Blender，直接喺 Blender 入面生成再修，比 ComfyUI 順手。

### 4.3 最重要嘅一段：生成 ≠ Game-ready

呢個係 3D 生成最大嘅誤解。AI 生成出嚟嘅 mesh **唔可以直接掉入遊戲引擎**：

| 問題 | 說明 | 解決 |
|---|---|---|
| **Topology 混亂** | 生成嘅係 marching cubes 出嚟嘅密集三角面，冇 edge flow | Retopology（Blender Quad Remesher / InstaLOD） |
| **面數過高** | 動輒 100k+ 三角面，遊戲角色通常要 5k–30k | Decimate + 人手修 |
| **UV 展開差** | 自動 UV 有拉伸、接縫難看 | 重新 UV unwrap |
| **冇 rigging** | 冇骨架，唔郁得 | Mixamo 自動 rig（人形），非人形要人手 |
| **LOD 缺失** | 冇多層次細節 | 引擎自動生成或人手做 |
| **PBR 材質不完整** | 部分模型只出 base color | Hunyuan3D 2.1 有完整 PBR，其他要補 |

**現實估算**：AI 生成慳咗「由零建模」嗰步（可能係 40% 工時），但後製仍然需要識 3D 嘅人。如果 team 入面冇人識 Blender，3D 呢條線建議暫緩。

**適合場景**：
- ✅ Prototype / greybox 階段快速填充場景
- ✅ 背景道具（唔近距離睇嘅嘢）
- ✅ 概念驗證、提案 demo
- ❌ 主角模型、近鏡物件、需要動畫嘅嘢

### 4.4 商用 API 替代

如果只係間中要幾個模型，**Meshy / Tripo / Rodin** 呢啲服務已經整合埋 retopology 同 UV，出嚟更接近 game-ready，計埋你嘅工時可能比自建平。

---

## 5. 分階段執行計劃

### Phase 0 — 評估（1 週，成本 < US$100）

- [ ] 租雲端 4090，裝 ComfyUI
- [ ] 跑通 FLUX schnell 出圖
- [ ] 跑通 TRELLIS 或 Hunyuan3D 出一個 3D model
- [ ] 用 Runway/Kling 免費額度試影片
- [ ] **產出**：一份「我哋實際做得到乜」嘅內部評估，帶埋樣本

**Phase 0 出口條件**：能夠答到「客戶問我哋做唔做到 X，答案係咩」。

### Phase 1 — 2D 自建（3–4 週）

- [ ] 決定買機定長租雲端
- [ ] ComfyUI API 接入 Laravel（queue + webhook）
- [ ] 訓練第一個風格 LoRA
- [ ] 建立去背 / 規格化後製 pipeline
- [ ] Vue 前端：提交任務 → 睇進度 → 下載結果
- [ ] **產出**：可以 demo 俾客戶睇嘅 2D 素材生成工具

### Phase 2 — 按需求擴展

只有喺有**具體付費客戶需求**嘅前提下先做：

- 3D：需要 team 有人識 Blender 後製
- 影片：需要月產量支撐自建，否則繼續用 API

---

## 6. 授權風險清單（做商業產品必讀）

呢個係最容易出事嘅地方 —— 用咗非商用模型做商業交付，客戶被追究時你要負責。

| 一定安全（Apache 2.0 / MIT） | 要小心 |
|---|---|
| FLUX.1 **schnell** | FLUX.1 **dev**（非商用，商用要買 licence） |
| Wan 2.1 / 2.2 | HunyuanVideo（Tencent Community License） |
| CogVideoX | LTX-2.3（LTX License，逐條睇） |
| TRELLIS（MIT） | Hunyuan3D（Tencent Community License） |
| InstantMesh | SD 系列（Stability Community License，有收入門檻） |

**必做**：每個模型部署前，去 HuggingFace model card 睇當下嘅 LICENSE 檔案 —— **授權條款會改**，網上文章嘅資訊可能過時。

另外兩個常被忽略嘅風險：

1. **訓練資料來源** —— 你用客戶素材 train LoRA，要喺合約寫清楚 IP 歸屬同使用範圍
2. **生成內容 IP** —— 生成圖像喺唔同司法區嘅版權地位仍未定案；交付俾客戶前應喺合約講清楚

> 以上唔構成法律意見。做商業交付前建議搵律師睇合約條款，尤其係 Creative Rights Copilot 嗰條線 —— 諷刺嘅係，你哋自己用生成 AI 都要面對同一批版權問題。

---

## 7. 成本快速估算

| 情境 | 月成本 |
|---|---|
| 雲端 4090，每月用 50 小時 | ~US$30 |
| 雲端 4090，每月用 300 小時 | ~US$180 |
| 自購 4090 攤三年 + 電費 | ~US$90/月 |
| 影片 API，每月 200 條 5 秒片 | ~US$50–100 |
| 3D API（Meshy 等），每月 100 個資產 | ~US$20–60 |

**結論**：2D 自建喺用量上到之後好快回本；影片同 3D 除非有規模，否則 API 長期更平。

---

## 8. 立即可做嘅三件事

1. 租一部雲端 4090 幾個鐘，裝 ComfyUI + FLUX schnell，親手出一張圖 —— 唔好淨係睇文章，實際跑一次先知瓶頸喺邊
2. 諗清楚**邊個客戶需求真係要多媒體生成** —— 如果目前 pipeline 入面冇人為呢樣嘢俾錢，Phase 1 應該押後，優先做文件分析嗰條線
3. 如果決定做 2D，收集 30 張風格參考圖，訓練第一個 LoRA —— 呢個係最快能夠展示「我哋做到 API 做唔到嘅嘢」嘅證明
