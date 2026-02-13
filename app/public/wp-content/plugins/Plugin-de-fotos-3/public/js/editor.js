/**
 * Editor JavaScript for Polaroids Customizadas
 * Handles photo upload, cropping, text editing, and submission
 */

(function () {
    'use strict';

    const EXPORT_TEXT_AREA_WIDTH = 376;
    const EXPORT_TEXT_AREA_HEIGHT = 80;

    // State management
    const state = {
        orderId: 0,
        hasBorder: false,
        photoQuantity: 0,
        photos: [],
        currentPhotoIndex: -1,
        cropper: null,
        cropperReady: false,
        pendingZoom: null,
        isDragging: false,
        isLoading: false,
        isSubmitting: false,
        submitProgress: 0,
        finalPreviewTimer: null,
        selectedPhotos: new Set(),
        cropperGestureSaveTimer: null,
        cropperStateSaveTimer: null,
        editorOverlayTimer: null,
        editorOverlayActiveKey: 'A',
        isProgrammaticTransform: false,
        editorOverlayHiddenDuringGesture: false,
        gestureStartData: null,
        gestureStartCanvas: null,
        cropperLoadToken: 0,
        lastNavigateAt: 0,
        lastNavInputAt: 0,
        lastThumbSelectAt: 0
    };

    /**
     * HistoryManager - Per-photo undo/redo functionality
     */
    class HistoryManager {
        constructor() {
            this.maxStackSize = 20;
        }

        /**
         * Get current photo
         */
        get currentPhoto() {
            return state.photos[state.currentPhotoIndex];
        }

        /**
         * Initialize history stacks for a photo
         */
        initPhoto(photo) {
            if (!photo.undoStack) photo.undoStack = [];
            if (!photo.redoStack) photo.redoStack = [];
        }

        /**
         * Create a snapshot of the current photo state
         */
        createSnapshot(photo) {
            return {
                zoom: photo.zoom || 1,
                rotation: photo.rotation || 0,
                textLayers: photo.textLayers ? JSON.parse(JSON.stringify(photo.textLayers)) : [],
                emojiLayers: photo.emojiLayers ? JSON.parse(JSON.stringify(photo.emojiLayers)) : [],
                cropData: photo.cropData ? JSON.parse(JSON.stringify(photo.cropData)) : null,
                canvasData: photo.canvasData ? JSON.parse(JSON.stringify(photo.canvasData)) : null
            };
        }

        /**
         * Save current state to undo stack before making changes
         */
        saveState() {
            const photo = this.currentPhoto;
            if (!photo) return;

            this.initPhoto(photo);

            const snapshot = this.createSnapshot(photo);
            photo.undoStack.push(snapshot);

            // Limit stack size
            if (photo.undoStack.length > this.maxStackSize) {
                photo.undoStack.shift();
            }

            // Clear redo stack when new action is performed
            photo.redoStack = [];

            this.updateButtons();
        }

        /**
         * Undo last action
         */
        undo() {
            const photo = this.currentPhoto;
            if (!photo || !photo.undoStack || photo.undoStack.length === 0) return;

            // Save current state to redo stack
            const currentSnapshot = this.createSnapshot(photo);
            if (!photo.redoStack) photo.redoStack = [];
            photo.redoStack.push(currentSnapshot);

            // Restore previous state
            const previousSnapshot = photo.undoStack.pop();
            this.restoreSnapshot(photo, previousSnapshot);

            this.updateButtons();
        }

        /**
         * Redo last undone action
         */
        redo() {
            const photo = this.currentPhoto;
            if (!photo || !photo.redoStack || photo.redoStack.length === 0) return;

            // Save current state to undo stack
            const currentSnapshot = this.createSnapshot(photo);
            if (!photo.undoStack) photo.undoStack = [];
            photo.undoStack.push(currentSnapshot);

            // Restore next state
            const nextSnapshot = photo.redoStack.pop();
            this.restoreSnapshot(photo, nextSnapshot);

            this.updateButtons();
        }

        /**
         * Restore a snapshot to the photo
         */
        restoreSnapshot(photo, snapshot) {
            photo.zoom = snapshot.zoom;
            photo.rotation = snapshot.rotation;
            photo.textLayers = snapshot.textLayers ? JSON.parse(JSON.stringify(snapshot.textLayers)) : [];
            photo.emojiLayers = snapshot.emojiLayers ? JSON.parse(JSON.stringify(snapshot.emojiLayers)) : [];
            photo.cropData = snapshot.cropData ? JSON.parse(JSON.stringify(snapshot.cropData)) : null;
            photo.canvasData = snapshot.canvasData ? JSON.parse(JSON.stringify(snapshot.canvasData)) : null;

            // Reload the photo in the editor to reflect changes
            if (state.cropper) {
                loadImageInCropper(photo);
            }

            // Update text layers
            if (state.hasBorder) {
                textManager.activeLayerIndex = -1;
                textManager.renderOverlay();
                textManager.renderControls(true);

                // Update emoji layers
                emojiManager.activeLayerIndex = -1;
                emojiManager.renderOverlay();
                emojiManager.renderControls(true);
            }

            // Update zoom slider
            if (elements.zoomSlider) {
                elements.zoomSlider.value = photo.zoom || 1;
            }
        }

        /**
         * Update undo/redo button states
         */
        updateButtons() {
            const photo = this.currentPhoto;
            const undoBtn = document.getElementById('undo-btn');
            const redoBtn = document.getElementById('redo-btn');

            if (undoBtn) {
                undoBtn.disabled = !photo || !photo.undoStack || photo.undoStack.length === 0;
            }
            if (redoBtn) {
                redoBtn.disabled = !photo || !photo.redoStack || photo.redoStack.length === 0;
            }
        }
    }

    const historyManager = new HistoryManager();

    // DOM Elements
    let elements = {};

    class TextLayerManager {
        constructor() {
            this.activeLayerIndex = -1;
            this.isDragging = false;
            this.dragStartX = 0;
            this.dragStartY = 0;
            this.dragStartLayerX = 0;
            this.dragStartLayerY = 0;
        }

        get currentPhoto() {
            return state.photos[state.currentPhotoIndex];
        }

        init(container) {
            this.container = container;
            this.overlay = document.getElementById('text-overlay-layer');
            this.controlsContainer = document.getElementById('text-controls-container');

            if (!this.overlay) {
                this.overlay = document.querySelector('#text-overlay-layer');
            }

            if (!this.overlay) {
                const polaroidFrame = document.querySelector('.sdpp-polaroid-frame');
                if (polaroidFrame) {
                    this.overlay = document.createElement('div');
                    this.overlay.id = 'text-overlay-layer';
                    this.overlay.className = 'sdpp-text-overlay';
                    polaroidFrame.appendChild(this.overlay);
                }
            }

            if (this.overlay) {
                // Global move/end listeners to handle dragging outside the element
                document.addEventListener('mousemove', this.handleDragMove.bind(this));
                document.addEventListener('mouseup', this.handleDragEnd.bind(this));
                document.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
                document.addEventListener('touchend', this.handleDragEnd.bind(this));
            }

            this.renderControls(true);
        }

        renderControls(fullRender = false) {
            if (!this.controlsContainer) return;

            // Structure: Header + List + Tools wrapper
            // Only rebuild Header + List if fullRender is true
            if (fullRender || !this.controlsContainer.querySelector('.sdpp-text-layers-list')) {
                const layers = this.currentPhoto?.textLayers || [];

                let html = `
                    <div class="sdpp-text-layers-header">
                        <h4>${sdppEditor.i18n.textLayers} (${layers.length}/4)</h4>
                        <button id="add-text-btn" class="sdpp-btn sdpp-btn-sm" ${layers.length >= 4 ? 'disabled' : ''}>${sdppEditor.i18n.add}</button>
                    </div>
                    <div class="sdpp-text-layers-list">
                        ${layers.map((layer, index) => `
                            <div class="sdpp-text-layer-item ${index === this.activeLayerIndex ? 'active' : ''}" data-index="${index}">
                                <input type="text" class="sdpp-layer-input" value="${layer.text}" placeholder="${sdppEditor.i18n.textPlaceholder}" data-index="${index}">
                                <button class="sdpp-layer-delete" data-index="${index}">×</button>
                            </div>
                        `).join('')}
                    </div>
                    <div id="sdpp-text-tools-wrapper"></div>
                `;

                this.controlsContainer.innerHTML = html;

                // Bind list events
                document.getElementById('add-text-btn')?.addEventListener('click', () => this.addLayer());

                this.controlsContainer.querySelectorAll('.sdpp-layer-input').forEach(input => {
                    input.addEventListener('input', (e) => this.updateLayerText(e));
                    input.addEventListener('focus', (e) => this.setActiveLayer(parseInt(e.target.dataset.index)));
                });

                this.controlsContainer.querySelectorAll('.sdpp-layer-delete').forEach(btn => {
                    btn.addEventListener('click', (e) => this.deleteLayer(parseInt(e.target.dataset.index)));
                });
            } else {
                // Just update list selection visual
                this.updateLayerListHighlight();
            }

            // Always render tools
            this.renderTools();
        }

        updateLayerListHighlight() {
            if (!this.controlsContainer) return;
            const items = this.controlsContainer.querySelectorAll('.sdpp-text-layer-item');
            items.forEach((item, index) => {
                item.classList.toggle('active', index === this.activeLayerIndex);
            });
        }

        renderTools() {
            const wrapper = document.getElementById('sdpp-text-tools-wrapper');
            if (!wrapper) return;

            const layers = this.currentPhoto?.textLayers || [];
            if (this.activeLayerIndex !== -1 && layers[this.activeLayerIndex]) {
                const layer = layers[this.activeLayerIndex];

                wrapper.innerHTML = `
                    <div class="sdpp-text-tools">
                        <div class="sdpp-form-group">
                            <label>${sdppEditor.i18n.font || 'Font'}</label>
                            <select id="layer-font" class="sdpp-form-control">
                                <option value="Pacifico" ${layer.fontFamily === 'Pacifico' ? 'selected' : ''}>Pacifico</option>
                                <option value="Dancing Script" ${layer.fontFamily === 'Dancing Script' ? 'selected' : ''}>Dancing Script</option>
                                <option value="Caveat" ${layer.fontFamily === 'Caveat' ? 'selected' : ''}>Caveat</option>
                                <option value="Amatic SC" ${layer.fontFamily === 'Amatic SC' ? 'selected' : ''}>Amatic SC</option>
                                <option value="Indie Flower" ${layer.fontFamily === 'Indie Flower' ? 'selected' : ''}>Indie Flower</option>
                                <option value="Shadows Into Light" ${layer.fontFamily === 'Shadows Into Light' ? 'selected' : ''}>Shadows Into Light</option>
                                <option value="Permanent Marker" ${layer.fontFamily === 'Permanent Marker' ? 'selected' : ''}>Permanent Marker</option>
                                <option value="Satisfy" ${layer.fontFamily === 'Satisfy' ? 'selected' : ''}>Satisfy</option>
                                <option value="Courgette" ${layer.fontFamily === 'Courgette' ? 'selected' : ''}>Courgette</option>
                                <option value="Great Vibes" ${layer.fontFamily === 'Great Vibes' ? 'selected' : ''}>Great Vibes</option>
                                <option value="Sacramento" ${layer.fontFamily === 'Sacramento' ? 'selected' : ''}>Sacramento</option>
                                <option value="Handlee" ${layer.fontFamily === 'Handlee' ? 'selected' : ''}>Handlee</option>
                                <option value="Kalam" ${layer.fontFamily === 'Kalam' ? 'selected' : ''}>Kalam</option>
                                <option value="Patrick Hand" ${layer.fontFamily === 'Patrick Hand' ? 'selected' : ''}>Patrick Hand</option>
                                <option value="Gloria Hallelujah" ${layer.fontFamily === 'Gloria Hallelujah' ? 'selected' : ''}>Gloria Hallelujah</option>
                                <option value="Roboto" ${layer.fontFamily === 'Roboto' ? 'selected' : ''}>Roboto</option>
                                <option value="Open Sans" ${layer.fontFamily === 'Open Sans' ? 'selected' : ''}>Open Sans</option>
                                <option value="Lato" ${layer.fontFamily === 'Lato' ? 'selected' : ''}>Lato</option>
                                <option value="Montserrat" ${layer.fontFamily === 'Montserrat' ? 'selected' : ''}>Montserrat</option>
                                <option value="Oswald" ${layer.fontFamily === 'Oswald' ? 'selected' : ''}>Oswald</option>
                            </select>
                        </div>
                        <div class="sdpp-form-group">
                            <label>${sdppEditor.i18n.color || 'Color'}</label>
                            <input type="color" id="layer-color" class="sdpp-color-picker" value="${layer.color || '#000000'}">
                        </div>
                        <div class="sdpp-form-group">
                            <label>${sdppEditor.i18n.size || 'Size'}</label>
                            <input type="range" id="layer-size" min="12" max="120" value="${layer.size || 28}">
                        </div>
                        <div class="sdpp-form-group">
                            <label>${sdppEditor.i18n.rotation || 'Girar'}</label>
                            <input type="range" id="layer-rotate" min="-180" max="180" value="${layer.rotation || 0}">
                        </div>
                        <div class="sdpp-tools-row">
                            <button id="layer-bold" class="sdpp-tool-btn ${layer.bold ? 'active' : ''}">B</button>
                            <button id="layer-italic" class="sdpp-tool-btn ${layer.italic ? 'active' : ''}">I</button>
                        </div>
                    </div>
                `;
                this.bindToolEvents();
            } else {
                wrapper.innerHTML = '';
            }
        }

        bindToolEvents() {
            const fontSelect = document.getElementById('layer-font');
            const colorPicker = document.getElementById('layer-color');
            const sizeSlider = document.getElementById('layer-size');
            const rotateSlider = document.getElementById('layer-rotate');
            const boldBtn = document.getElementById('layer-bold');
            const italicBtn = document.getElementById('layer-italic');

            if (fontSelect) {
                fontSelect.addEventListener('change', (e) => this.updateLayerStyle('fontFamily', e.target.value));
            }
            if (colorPicker) {
                colorPicker.addEventListener('input', (e) => this.updateLayerStyle('color', e.target.value));
            }
            if (sizeSlider) {
                sizeSlider.addEventListener('input', (e) => this.updateLayerStyle('size', parseInt(e.target.value)));
            }
            if (rotateSlider) {
                rotateSlider.addEventListener('input', (e) => this.updateLayerStyle('rotation', parseInt(e.target.value)));
            }
            if (boldBtn) {
                boldBtn.addEventListener('click', () => this.updateLayerStyle('bold', !this.currentPhoto.textLayers[this.activeLayerIndex].bold));
            }
            if (italicBtn) {
                italicBtn.addEventListener('click', () => this.updateLayerStyle('italic', !this.currentPhoto.textLayers[this.activeLayerIndex].italic));
            }
        }

        addLayer() {
            if (!this.currentPhoto) return;
            if (!this.currentPhoto.textLayers) this.currentPhoto.textLayers = [];
            if (this.currentPhoto.textLayers.length >= 4) return;

            // Save state before change
            historyManager.saveState();

            this.currentPhoto.textLayers.push({
                type: 'text',
                text: sdppEditor.i18n.newText || 'New Text',
                fontFamily: 'Pacifico',
                color: '#000000',
                size: 28,
                bold: false,
                italic: false,
                rotation: 0,
                x: 0,
                y: 0
            });

            this.activeLayerIndex = this.currentPhoto.textLayers.length - 1;
            this.renderOverlay();
            this.renderControls(true); // Rebuild list
        }

        deleteLayer(index) {
            if (!this.currentPhoto || !this.currentPhoto.textLayers) return;

            // Save state before change
            historyManager.saveState();

            this.currentPhoto.textLayers.splice(index, 1);
            this.activeLayerIndex = -1;
            this.renderOverlay();
            this.renderControls(true); // Rebuild list
        }

        setActiveLayer(index) {
            if (this.activeLayerIndex === index) return;
            this.activeLayerIndex = index;

            // Deselect emoji layer when text is selected
            if (emojiManager) {
                emojiManager.activeLayerIndex = -1;
                emojiManager.renderOverlay();
                emojiManager.renderControls(false);
            }

            this.renderOverlay(); // To highlight active element
            this.renderControls(false); // Only update highlights and tools
        }

        updateLayerText(e) {
            const index = parseInt(e.target.dataset.index);
            if (this.currentPhoto && this.currentPhoto.textLayers[index]) {
                this.currentPhoto.textLayers[index].text = e.target.value;
                this.renderOverlay();
            }
        }

        updateLayerStyle(prop, value) {
            if (this.activeLayerIndex !== -1 && this.currentPhoto.textLayers[this.activeLayerIndex]) {
                // Save state before change
                historyManager.saveState();

                this.currentPhoto.textLayers[this.activeLayerIndex][prop] = value;
                this.renderOverlay();
                // If bold/italic, re-render tools to update button state
                if (prop === 'bold' || prop === 'italic') {
                    this.renderTools();
                }
            }
        }

        renderOverlay() {
            if (!this.overlay || !this.currentPhoto) return;

            const layers = this.currentPhoto.textLayers || [];

            // Clear existing content
            this.overlay.innerHTML = '';

            layers.forEach((layer, index) => {
                const el = document.createElement('div');
                el.className = `sdpp-text-element ${index === this.activeLayerIndex ? 'active' : ''}`;
                el.dataset.index = index;
                el.textContent = layer.text;

                // Apply styles
                el.style.fontFamily = `"${layer.fontFamily}", cursive`;
                el.style.color = layer.color;
                el.style.fontSize = `${layer.size}px`;
                el.style.fontWeight = layer.bold ? 'bold' : 'normal';
                el.style.fontStyle = layer.italic ? 'italic' : 'normal';

                if (state.gridType === '2x3' && state.hasBorder && window.matchMedia('(max-width: 768px)').matches) {
                    el.style.transformOrigin = '0 85%';
                }
                el.style.transform = `translate(${layer.x}px, ${layer.y}px) rotate(${layer.rotation || 0}deg)`;

                // Direct Event Binding - The most robust way
                el.addEventListener('mousedown', this.handleDragStart.bind(this));
                el.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: false });

                this.overlay.appendChild(el);
            });
        }

        handleDragStart(e) {
            e.preventDefault();
            e.stopPropagation();

            const element = e.target.closest('.sdpp-text-element');
            if (!element) return;

            this.isDragging = true;
            this.activeLayerIndex = parseInt(element.dataset.index);

            // Deselect emoji
            if (emojiManager) {
                emojiManager.activeLayerIndex = -1;
                emojiManager.renderOverlay();
                emojiManager.renderControls(false);
            }

            this.renderOverlay(); // Re-render to update active class
            this.renderControls(false);

            this.dragStartX = e.clientX;
            this.dragStartY = e.clientY;

            const layer = this.currentPhoto.textLayers[this.activeLayerIndex];
            this.dragStartLayerX = layer.x || 0;
            this.dragStartLayerY = layer.y || 0;
        }

        handleTouchStart(e) {
            // Prevent default to stop scrolling immediately
            if (e.cancelable) e.preventDefault();
            e.stopPropagation();

            const element = e.target.closest('.sdpp-text-element');
            if (!element) return;

            this.isDragging = true;
            this.dragElement = element; // Store reference to the element being dragged
            this.activeLayerIndex = parseInt(element.dataset.index);

            // Visual feedback - add active class without re-rendering
            this.overlay.querySelectorAll('.sdpp-text-element').forEach(el => el.classList.remove('active'));
            element.classList.add('active');

            // Deselect emoji (but don't re-render text overlay!)
            if (emojiManager) {
                emojiManager.activeLayerIndex = -1;
                emojiManager.renderOverlay();
                emojiManager.renderControls(false);
            }

            // Only update controls, NOT the overlay
            this.renderControls(false);

            const touch = e.touches[0];
            this.dragStartX = touch.clientX;
            this.dragStartY = touch.clientY;

            const layer = this.currentPhoto.textLayers[this.activeLayerIndex];
            this.dragStartLayerX = layer.x || 0;
            this.dragStartLayerY = layer.y || 0;
        }

        handleDragMove(e) {
            if (!this.isDragging) return;
            e.preventDefault();
            this.updateDragPosition(e.clientX, e.clientY);
        }

        handleTouchMove(e) {
            if (!this.isDragging) return;
            if (e.cancelable) e.preventDefault();
            e.stopPropagation();

            const touch = e.touches[0];
            this.updateDragPosition(touch.clientX, touch.clientY);
        }

        updateDragPosition(clientX, clientY) {
            const deltaX = clientX - this.dragStartX;
            const deltaY = clientY - this.dragStartY;

            if (this.currentPhoto && this.currentPhoto.textLayers[this.activeLayerIndex]) {
                const layer = this.currentPhoto.textLayers[this.activeLayerIndex];

                // Calculate constraints
                // We need the element dimensions to clamp properly
                const element = this.overlay.querySelector(`.sdpp-text-element[data-index="${this.activeLayerIndex}"]`);
                if (element) {
                    const elWidth = element.offsetWidth;
                    const elHeight = element.offsetHeight;
                    const overlayRect = this.overlay.getBoundingClientRect();
                    const containerWidth = overlayRect.width;
                    const containerHeight = overlayRect.height;

                    // Persist the exact coordinate space used while editing (per photo)
                    // so export can normalize reliably on mobile.
                    if (this.currentPhoto) {
                        this.currentPhoto.editorFooterDims = { w: containerWidth, h: containerHeight };
                    }

                    // Top-Left based constraints
                    // x must be >= 0 and x + elWidth <= containerWidth
                    // so x <= containerWidth - elWidth
                    // We must clamp "newX" which is the CSS transform X position. 
                    // However, we need to be careful with coordinate spaces. 
                    // The simplest way is to trust deltaX/deltaY relative to start.

                    const minX = 0;
                    const maxX = Math.max(0, containerWidth - elWidth);
                    const minY = 0;
                    const maxY = Math.max(0, containerHeight - elHeight);

                    let newX = this.dragStartLayerX + deltaX;
                    let newY = this.dragStartLayerY + deltaY;

                    // Clamp
                    newX = Math.max(minX, Math.min(maxX, newX));
                    newY = Math.max(minY, Math.min(maxY, newY));

                    layer.x = newX;
                    layer.y = newY;

                    // Direct DOM update for performance during drag
                    if (element) {
                        element.style.transform = `translate(${newX}px, ${newY}px) rotate(${layer.rotation || 0}deg)`;
                    }
                }
            }
        }

        handleDragEnd() {
            if (this.isDragging) {
                this.isDragging = false;
                this.dragElement = null;
                // Re-render to sync state after drag completes
                this.renderOverlay();
            }
        }
    }

    const textManager = new TextLayerManager();

    /**
     * EmojiLayerManager - Independent emoji layer management
     */
    class EmojiLayerManager {
        constructor() {
            this.activeLayerIndex = -1;
            this.isDragging = false;
            this.dragStartX = 0;
            this.dragStartY = 0;
            this.dragStartLayerX = 0;
            this.dragStartLayerY = 0;
        }

        get currentPhoto() {
            return state.photos[state.currentPhotoIndex];
        }

        init(container) {
            this.container = container;
            this.overlay = document.getElementById('emoji-overlay-layer');
            this.controlsContainer = document.getElementById('emoji-controls-container');

            if (!this.overlay) {
                const polaroidFooter = document.querySelector('.sdpp-polaroid-footer');
                if (polaroidFooter) {
                    this.overlay = document.createElement('div');
                    this.overlay.id = 'emoji-overlay-layer';
                    this.overlay.className = 'sdpp-emoji-overlay';
                    polaroidFooter.appendChild(this.overlay);
                }
            }

            if (this.overlay) {
                // Global move/end listeners
                document.addEventListener('mousemove', this.handleDragMove.bind(this));
                document.addEventListener('mouseup', this.handleDragEnd.bind(this));
                document.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
                document.addEventListener('touchend', this.handleDragEnd.bind(this));
            }

            this.renderControls(true);
        }

        renderOverlay() {
            if (!this.overlay || !this.currentPhoto) return;

            const layers = this.currentPhoto.emojiLayers || [];

            // Clear
            this.overlay.innerHTML = '';

            layers.forEach((layer, index) => {
                const el = document.createElement('div');
                el.className = `sdpp-emoji-element ${index === this.activeLayerIndex ? 'active' : ''}`;
                el.dataset.index = index;

                el.style.width = `${layer.size}px`;
                el.style.height = `${layer.size}px`;
                el.style.transform = `translate(${layer.x}px, ${layer.y}px)`;

                // Add Image
                const img = document.createElement('img');
                img.src = layer.imageSrc;
                img.alt = layer.name || 'emoji';
                img.draggable = false;
                el.appendChild(img);

                // Direct Binding
                el.addEventListener('mousedown', this.handleDragStart.bind(this));
                el.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: false });

                this.overlay.appendChild(el);
            });
        }

        renderControls(fullRender = false) {
            if (!this.controlsContainer) return;

            if (fullRender || !this.controlsContainer.querySelector('.sdpp-emoji-layers-list')) {
                const layers = this.currentPhoto?.emojiLayers || [];

                let html = `
                    <div class="sdpp-emoji-layers-list">
                        ${layers.map((layer, index) => `
                            <div class="sdpp-emoji-layer-item ${index === this.activeLayerIndex ? 'active' : ''}" data-index="${index}">
                                <img src="${layer.imageSrc}" alt="${layer.name || 'emoji'}" class="sdpp-emoji-preview-img" style="width: 24px; height: 24px; object-fit: contain;">
                                <button class="sdpp-layer-delete" data-index="${index}">×</button>
                            </div>
                        `).join('')}
                    </div>
                    <div id="sdpp-emoji-tools-wrapper"></div>
                `;

                this.controlsContainer.innerHTML = html;

                // Bind list events
                this.controlsContainer.querySelectorAll('.sdpp-emoji-layer-item').forEach(item => {
                    item.addEventListener('click', (e) => {
                        if (!e.target.classList.contains('sdpp-layer-delete')) {
                            this.setActiveLayer(parseInt(item.dataset.index));
                        }
                    });
                });

                this.controlsContainer.querySelectorAll('.sdpp-layer-delete').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.deleteLayer(parseInt(e.target.dataset.index));
                    });
                });
            } else {
                this.updateLayerListHighlight();
            }

            this.renderTools();
        }

        updateLayerListHighlight() {
            if (!this.controlsContainer) return;
            const items = this.controlsContainer.querySelectorAll('.sdpp-emoji-layer-item');
            items.forEach((item, index) => {
                item.classList.toggle('active', index === this.activeLayerIndex);
            });
        }

        renderTools() {
            const wrapper = document.getElementById('sdpp-emoji-tools-wrapper');
            if (!wrapper) return;

            const layers = this.currentPhoto?.emojiLayers || [];
            if (this.activeLayerIndex !== -1 && layers[this.activeLayerIndex]) {
                const layer = layers[this.activeLayerIndex];

                wrapper.innerHTML = `
                    <div class="sdpp-emoji-tools">
                        <div class="sdpp-form-group">
                            <label>${sdppEditor.i18n.size || 'Tamanho'}</label>
                            <input type="range" id="emoji-size" min="24" max="120" value="${layer.size || 48}">
                        </div>
                        <div class="sdpp-tools-row">
                            <button id="emoji-duplicate" class="sdpp-btn sdpp-btn-sm">${sdppEditor.i18n.duplicate || 'Duplicar'}</button>
                        </div>
                    </div>
                `;
                this.bindToolEvents();
            } else {
                wrapper.innerHTML = '';
            }
        }

        bindToolEvents() {
            const sizeSlider = document.getElementById('emoji-size');
            const duplicateBtn = document.getElementById('emoji-duplicate');

            if (sizeSlider) {
                sizeSlider.addEventListener('input', (e) => this.updateLayerStyle('size', parseInt(e.target.value)));
            }
            if (duplicateBtn) {
                duplicateBtn.addEventListener('click', () => this.duplicateLayer());
            }
        }

        addEmoji(imageSrc, emojiName) {
            if (!this.currentPhoto) return;
            if (!this.currentPhoto.emojiLayers) this.currentPhoto.emojiLayers = [];

            historyManager.saveState();

            this.currentPhoto.emojiLayers.push({
                imageSrc: imageSrc,
                name: emojiName || 'emoji',
                size: 48,
                x: 0,
                y: 0
            });

            this.activeLayerIndex = this.currentPhoto.emojiLayers.length - 1;
            this.renderOverlay();
            this.renderControls(true);
        }

        duplicateLayer() {
            if (this.activeLayerIndex === -1 || !this.currentPhoto.emojiLayers) return;

            const layer = this.currentPhoto.emojiLayers[this.activeLayerIndex];

            historyManager.saveState();

            this.currentPhoto.emojiLayers.push({
                imageSrc: layer.imageSrc,
                name: layer.name,
                size: layer.size,
                x: layer.x + 20,
                y: layer.y + 20
            });

            this.activeLayerIndex = this.currentPhoto.emojiLayers.length - 1;
            this.renderOverlay();
            this.renderControls(true);
        }

        deleteLayer(index) {
            if (!this.currentPhoto || !this.currentPhoto.emojiLayers) return;

            historyManager.saveState();

            this.currentPhoto.emojiLayers.splice(index, 1);
            this.activeLayerIndex = -1;
            this.renderOverlay();
            this.renderControls(true);
        }

        setActiveLayer(index) {
            if (this.activeLayerIndex === index) return;
            this.activeLayerIndex = index;

            if (textManager) {
                textManager.activeLayerIndex = -1;
                textManager.renderOverlay();
                textManager.renderControls(false);
            }

            this.renderOverlay();
            this.renderControls(false);
        }

        updateLayerStyle(prop, value) {
            if (this.activeLayerIndex !== -1 && this.currentPhoto.emojiLayers[this.activeLayerIndex]) {
                historyManager.saveState();
                this.currentPhoto.emojiLayers[this.activeLayerIndex][prop] = value;
                this.renderOverlay();
            }
        }

        handleDragStart(e) {
            e.preventDefault();
            e.stopPropagation();

            const element = e.target.closest('.sdpp-emoji-element');
            if (!element) return;

            this.isDragging = true;
            this.activeLayerIndex = parseInt(element.dataset.index);

            // Deselect text layer
            if (textManager) {
                textManager.activeLayerIndex = -1;
                textManager.renderOverlay();
                textManager.renderControls(false);
            }

            this.renderOverlay();
            this.renderControls(false);

            this.dragStartX = e.clientX;
            this.dragStartY = e.clientY;

            const layer = this.currentPhoto.emojiLayers[this.activeLayerIndex];
            this.dragStartLayerX = layer.x || 0;
            this.dragStartLayerY = layer.y || 0;
        }

        handleTouchStart(e) {
            // Prevent default to stop scrolling immediately
            if (e.cancelable) e.preventDefault();
            e.stopPropagation();

            const element = e.target.closest('.sdpp-emoji-element');
            if (!element) return;

            this.isDragging = true;
            this.dragElement = element; // Store reference
            this.activeLayerIndex = parseInt(element.dataset.index);

            // Visual feedback - add active class without re-rendering
            this.overlay.querySelectorAll('.sdpp-emoji-element').forEach(el => el.classList.remove('active'));
            element.classList.add('active');

            // Deselect text layer (but don't re-render emoji overlay!)
            if (textManager) {
                textManager.activeLayerIndex = -1;
                textManager.renderOverlay();
                textManager.renderControls(false);
            }

            // Only update controls, NOT the overlay
            this.renderControls(false);

            const touch = e.touches[0];
            this.dragStartX = touch.clientX;
            this.dragStartY = touch.clientY;

            const layer = this.currentPhoto.emojiLayers[this.activeLayerIndex];
            this.dragStartLayerX = layer.x || 0;
            this.dragStartLayerY = layer.y || 0;
        }

        handleDragMove(e) {
            if (!this.isDragging) return;
            this.updateDragPosition(e.clientX, e.clientY);
        }

        handleTouchMove(e) {
            if (!this.isDragging) return;
            if (e.cancelable) e.preventDefault(); // Prevent scrolling
            e.stopPropagation();

            const touch = e.touches[0];
            this.updateDragPosition(touch.clientX, touch.clientY);
        }

        updateDragPosition(clientX, clientY) {
            const deltaX = clientX - this.dragStartX;
            const deltaY = clientY - this.dragStartY;

            if (this.currentPhoto && this.currentPhoto.emojiLayers[this.activeLayerIndex]) {
                const layer = this.currentPhoto.emojiLayers[this.activeLayerIndex];

                const element = this.overlay.querySelector(`.sdpp-emoji-element[data-index="${this.activeLayerIndex}"]`);
                if (element) {
                    const elWidth = element.offsetWidth;
                    const elHeight = element.offsetHeight;
                    const overlayRect = this.overlay.getBoundingClientRect();
                    const containerWidth = overlayRect.width;
                    const containerHeight = overlayRect.height;

                    if (this.currentPhoto) {
                        this.currentPhoto.editorFooterDims = { w: containerWidth, h: containerHeight };
                    }

                    const minX = 0;
                    const maxX = Math.max(0, containerWidth - elWidth);
                    const minY = 0;
                    const maxY = Math.max(0, containerHeight - elHeight);

                    let newX = this.dragStartLayerX + deltaX;
                    let newY = this.dragStartLayerY + deltaY;

                    // Clamp
                    newX = Math.max(minX, Math.min(maxX, newX));
                    newY = Math.max(minY, Math.min(maxY, newY));

                    layer.x = newX;
                    layer.y = newY;

                    // Direct DOM update for performance
                    if (element) {
                        element.style.transform = `translate(${newX}px, ${newY}px)`;
                    }
                }
            }
        }

        handleDragEnd() {
            if (this.isDragging) {
                this.isDragging = false;
                this.dragElement = null;
                // Re-render to sync state after drag completes
                this.renderOverlay();
            }
        }
    }

    const emojiManager = new EmojiLayerManager();

    /**
     * Initialize the editor
     */
    function init() {
        const editor = document.getElementById('sdpp-editor');
        if (!editor) return;

        // Load state from data attributes
        state.orderId = parseInt(editor.dataset.orderId) || 0;
        state.hasBorder = editor.dataset.hasBorder === '1';
        state.photoQuantity = parseInt(editor.dataset.photoQuantity) || 9;
        state.gridType = editor.dataset.gridType || '3x3';

        // Cache DOM elements
        cacheElements();

        // Initialize Text Manager if border is enabled
        if (state.hasBorder) {
            const controlsContainer = document.getElementById('photo-editor-controls');
            // Create container for text controls if it doesn't exist
            let textControls = document.getElementById('text-controls-container');
            if (!textControls) {
                textControls = document.createElement('div');
                textControls.id = 'text-controls-container';
                controlsContainer.appendChild(textControls);
            }
            textManager.init();

            // Create container for emoji controls
            let emojiControls = document.getElementById('emoji-controls-container');
            if (!emojiControls) {
                emojiControls = document.createElement('div');
                emojiControls.id = 'emoji-controls-container';
                controlsContainer.appendChild(emojiControls);
            }
            emojiManager.init();
        }

        // Bind events
        bindEvents();

        console.log('SDPP Editor initialized', state);
    }

    /**
     * Cache frequently used DOM elements
     */
    function cacheElements() {
        elements = {
            uploadDropzone: document.getElementById('upload-dropzone'),
            uploadBtn: document.getElementById('upload-btn'),
            photoInput: document.getElementById('photo-input'),
            thumbnailsGrid: document.getElementById('thumbnails-grid'),
            emptyState: document.getElementById('empty-state'),
            photoEditor: document.getElementById('photo-editor'),
            editorFinalImage: document.getElementById('editor-final-image'),
            cropperImage: document.getElementById('cropper-image'),
            finalPreview: document.getElementById('final-preview'),
            finalPreviewImage: document.getElementById('final-preview-image'),
            zoomSlider: document.getElementById('zoom-slider'),
            currentPhotoLabel: document.getElementById('current-photo-label'),
            currentIndex: document.getElementById('current-index'),
            totalPhotos: document.getElementById('total-photos'),
            prevPhotoBtn: document.getElementById('prev-photo-btn'),
            nextPhotoBtn: document.getElementById('next-photo-btn'),
            photosUploaded: document.getElementById('photos-uploaded'),
            progressFill: document.getElementById('progress-fill'),
            footerStatus: document.getElementById('footer-status'),
            backBtn: document.getElementById('back-btn'),
            submitBtn: document.getElementById('submit-btn'),
            gridPreview: document.getElementById('grid-preview'),
            gridContainer: document.getElementById('grid-container'),
            successModal: document.getElementById('success-modal'),
            modalCloseBtn: document.getElementById('modal-close-btn'),
            saveEmailBtn: document.getElementById('save-email-btn'),
            customerEmail: document.getElementById('customer-email'),
            loadingOverlay: document.getElementById('loading-overlay'),
            loadingText: document.getElementById('loading-text'),
            bulkActions: document.getElementById('bulk-actions'),
            selectAllBtn: document.getElementById('select-all-btn'),
            deleteSelectedBtn: document.getElementById('delete-selected-btn'),
            duplicateSelectedBtn: document.getElementById('duplicate-selected-btn'),
            applyAllBtn: document.getElementById('apply-all-btn'),
            applyStyleBtn: document.getElementById('apply-style-btn'),
            addEmojiBtn: document.getElementById('add-emoji-btn'),
            emojiPickerContainer: document.getElementById('sdpp-emoji-picker-container'),
            uploadSection: document.querySelector('.sdpp-upload-section')
        };

        // Double-buffer the editor overlay image to prevent flicker on src swaps
        if (elements.editorFinalImage && elements.editorFinalImage.parentElement) {
            let buffer = document.getElementById('editor-final-image-buffer');
            if (!buffer) {
                buffer = elements.editorFinalImage.cloneNode(false);
                buffer.id = 'editor-final-image-buffer';
                buffer.src = '';
                buffer.style.opacity = '0';
                elements.editorFinalImage.parentElement.appendChild(buffer);
            }
            elements.editorFinalImageA = elements.editorFinalImage;
            elements.editorFinalImageB = buffer;
        }
    }

    function getActiveEditorOverlayEl() {
        if (elements.editorFinalImageA && elements.editorFinalImageB) {
            return (state.editorOverlayActiveKey === 'A') ? elements.editorFinalImageA : elements.editorFinalImageB;
        }
        return elements.editorFinalImage;
    }

    function getInactiveEditorOverlayEl() {
        if (elements.editorFinalImageA && elements.editorFinalImageB) {
            return (state.editorOverlayActiveKey === 'A') ? elements.editorFinalImageB : elements.editorFinalImageA;
        }
        return elements.editorFinalImage;
    }

    /**
     * Show loading overlay LOCALLY inside the cropper wrapper (not full-page)
     */
    function showInteractionLoading() {
        const wrapper = elements.cropperImage ? elements.cropperImage.closest('.sdpp-cropper-wrapper') : null;
        if (!wrapper) return;

        let overlay = wrapper.querySelector('.sdpp-cropper-loading-overlay');
        if (!overlay) {
            // Create the overlay element on first use
            overlay = document.createElement('div');
            overlay.className = 'sdpp-cropper-loading-overlay';
            overlay.innerHTML = '<div class="sdpp-cropper-loading-spinner"></div>';
            wrapper.appendChild(overlay);
        }
        overlay.style.display = 'flex';
    }

    /**
     * Hide local cropper loading overlay
     */
    function hideInteractionLoading() {
        const wrapper = elements.cropperImage ? elements.cropperImage.closest('.sdpp-cropper-wrapper') : null;
        if (!wrapper) return;
        const overlay = wrapper.querySelector('.sdpp-cropper-loading-overlay');
        if (overlay) overlay.style.display = 'none';
    }

    /**
     * Bind all event listeners
     */
    function bindEvents() {
        // Upload events
        elements.uploadBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            elements.photoInput.click();
        });
        elements.photoInput.addEventListener('change', handleFileSelect);

        // Drag and drop
        elements.uploadDropzone.addEventListener('dragover', handleDragOver);
        elements.uploadDropzone.addEventListener('dragleave', handleDragLeave);
        elements.uploadDropzone.addEventListener('drop', handleDrop);
        elements.uploadDropzone.addEventListener('click', (e) => {
            if (e.target === elements.uploadDropzone || e.target.closest('.sdpp-dropzone-content')) {
                elements.photoInput.click();
            }
        });

        // Navigation events
        // Mobile browsers can fire both pointer/touch AND click for one tap; de-dupe to prevent skipping.
        const navTap = (dir, e) => {
            if (e && e.cancelable) e.preventDefault();
            if (e) e.stopPropagation();
            if (dir < 0 && elements.prevPhotoBtn && elements.prevPhotoBtn.disabled) return;
            if (dir > 0 && elements.nextPhotoBtn && elements.nextPhotoBtn.disabled) return;
            state.lastNavInputAt = Date.now();
            navigatePhoto(dir);
        };

        if (elements.prevPhotoBtn) {
            elements.prevPhotoBtn.addEventListener('pointerup', (e) => {
                navTap(-1, e);
            }, { passive: false });
            elements.prevPhotoBtn.addEventListener('click', (e) => {
                // Ignore synthetic click that follows touch/pointer
                if (Date.now() - (state.lastNavInputAt || 0) < 500) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                navTap(-1, e);
            });
        }

        if (elements.nextPhotoBtn) {
            elements.nextPhotoBtn.addEventListener('pointerup', (e) => {
                navTap(1, e);
            }, { passive: false });
            elements.nextPhotoBtn.addEventListener('click', (e) => {
                if (Date.now() - (state.lastNavInputAt || 0) < 500) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                navTap(1, e);
            });
        }

        // Thumbnails click (delegation) - robust even when thumbnails are rebuilt
        if (elements.thumbnailsGrid) {
            elements.thumbnailsGrid.addEventListener('click', (e) => {
                const thumb = e.target && e.target.closest ? e.target.closest('.sdpp-thumbnail') : null;
                if (!thumb) return;

                // Mobile ghost/double click guard
                const now = Date.now();
                if (now - (state.lastThumbSelectAt || 0) < 350) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                state.lastThumbSelectAt = now;

                // Ignore clicks on checkbox/delete
                if (e.target && (e.target.classList.contains('sdpp-thumbnail-checkbox') || e.target.classList.contains('sdpp-thumbnail-delete'))) {
                    return;
                }

                const id = String(thumb.dataset.photoId || '');
                const liveIndex = state.photos.findIndex(p => String(p.id) === id);
                if (liveIndex !== -1) {
                    try {
                        selectPhoto(liveIndex);
                    } catch (err) {
                        console.error('Failed to select photo from thumbnail', err);
                    }
                }
            });
        }

        // Zoom controls
        const zoomInBtn = document.getElementById('zoom-in-btn');
        const zoomOutBtn = document.getElementById('zoom-out-btn');
        const zoomStep = parseFloat((elements.zoomSlider && elements.zoomSlider.step) ? elements.zoomSlider.step : '0.05');
        if (zoomInBtn) zoomInBtn.addEventListener('click', () => adjustZoom(zoomStep));
        if (zoomOutBtn) zoomOutBtn.addEventListener('click', () => adjustZoom(-zoomStep));
        if (elements.zoomSlider) {
            elements.zoomSlider.addEventListener('input', handleZoomSlider);
        }

        if (elements.cropperImage) {
            // Prevent pinch zoom (2 fingers) on the image; zoom should be controlled only by UI controls
            elements.cropperImage.addEventListener('touchstart', (e) => {
                if (e.touches && e.touches.length > 1) {
                    if (e.cancelable) e.preventDefault();
                    e.stopPropagation();
                }
            }, { passive: false });
            elements.cropperImage.addEventListener('touchmove', (e) => {
                if (e.touches && e.touches.length > 1) {
                    if (e.cancelable) e.preventDefault();
                    e.stopPropagation();
                }
            }, { passive: false });

            // Prevent single-finger panning on the preview (image move must be via move controls)
            elements.cropperImage.addEventListener('touchmove', (e) => {
                if (e.touches && e.touches.length === 1) {
                    if (e.cancelable) e.preventDefault();
                }
            }, { passive: false });

            elements.cropperImage.addEventListener('touchend', scheduleGestureSaveCropData, { passive: true });
            elements.cropperImage.addEventListener('pointerup', scheduleGestureSaveCropData, { passive: true });
        }

        // Rotation controls
        document.getElementById('rotate-left-btn').addEventListener('click', () => rotateImage(-90));
        document.getElementById('rotate-right-btn').addEventListener('click', () => rotateImage(90));

        // Move controls (reposition image without dragging on preview)
        const moveLeftBtn = document.getElementById('move-left-btn');
        const moveRightBtn = document.getElementById('move-right-btn');
        const moveUpBtn = document.getElementById('move-up-btn');
        const moveDownBtn = document.getElementById('move-down-btn');
        const moveStep = 6;
        if (moveLeftBtn) moveLeftBtn.addEventListener('click', () => nudgeImage(-moveStep, 0));
        if (moveRightBtn) moveRightBtn.addEventListener('click', () => nudgeImage(moveStep, 0));
        if (moveUpBtn) moveUpBtn.addEventListener('click', () => nudgeImage(0, -moveStep));
        if (moveDownBtn) moveDownBtn.addEventListener('click', () => nudgeImage(0, moveStep));

        // Undo/Redo controls
        const undoBtn = document.getElementById('undo-btn');
        const redoBtn = document.getElementById('redo-btn');
        if (undoBtn) {
            undoBtn.addEventListener('click', () => historyManager.undo());
        }
        if (redoBtn) {
            redoBtn.addEventListener('click', () => historyManager.redo());
        }

        // Text controls - handled by TextLayerManager now
        if (state.hasBorder) {

            if (elements.addEmojiBtn && elements.emojiPickerContainer) {
                // Toggle emoji tray visibility
                elements.addEmojiBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isHidden = elements.emojiPickerContainer.style.display === 'none';
                    elements.emojiPickerContainer.style.display = isHidden ? 'block' : 'none';
                });

                // Handle PNG emoji button clicks
                const emojiButtons = elements.emojiPickerContainer.querySelectorAll('.sdpp-emoji-item');
                emojiButtons.forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const imageSrc = btn.dataset.emojiSrc;
                        const emojiName = btn.dataset.emojiName;
                        emojiManager.addEmoji(imageSrc, emojiName);
                        elements.emojiPickerContainer.style.display = 'none';
                    });
                });

                // Close picker when clicking outside
                document.addEventListener('click', (e) => {
                    if (elements.emojiPickerContainer.style.display !== 'none' &&
                        !elements.emojiPickerContainer.contains(e.target) &&
                        e.target !== elements.addEmojiBtn) {
                        elements.emojiPickerContainer.style.display = 'none';
                    }
                });
            }
        }

        // Bulk actions
        elements.selectAllBtn.addEventListener('click', toggleSelectAll);
        elements.deleteSelectedBtn.addEventListener('click', deleteSelectedPhotos);
        if (elements.duplicateSelectedBtn) {
            elements.duplicateSelectedBtn.addEventListener('click', duplicateSelectedPhotos);
        }
        if (elements.applyStyleBtn) {
            elements.applyStyleBtn.addEventListener('click', applyStyleToSelected);
        }

        // Submit
        elements.submitBtn.addEventListener('click', submitOrder);

        // Modal actions
        if (elements.modalCloseBtn) {
            elements.modalCloseBtn.addEventListener('click', () => {
                elements.successModal.style.display = 'none';
            });
        }
        if (elements.saveEmailBtn) {
            elements.saveEmailBtn.addEventListener('click', saveEmail);
        }
    }

    async function saveEmail() {
        const email = (elements.customerEmail?.value || '').trim();
        if (!email) {
            alert('Digite seu e-mail.');
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            alert('Email inválido.');
            return;
        }

        if (!elements.saveEmailBtn) return;

        const originalText = elements.saveEmailBtn.textContent;
        elements.saveEmailBtn.disabled = true;
        elements.saveEmailBtn.textContent = 'Enviando...';

        try {
            const formData = new FormData();
            formData.append('action', 'sdpp_save_customer_email');
            formData.append('nonce', sdppEditor.nonce);
            formData.append('order_id', state.orderId);
            formData.append('customer_email', email);

            const res = await fetch(sdppEditor.ajaxUrl, {
                method: 'POST',
                body: formData
            });

            let json;
            try {
                json = await res.json();
            } catch (e) {
                const raw = await res.text();
                throw new Error(`Email save failed (invalid JSON). HTTP ${res.status}. Response: ${raw.slice(0, 400)}`);
            }

            if (!res.ok) {
                throw new Error(json?.data?.message || `Falha ao salvar email. HTTP ${res.status}`);
            }

            if (!json.success) {
                throw new Error(json?.data?.message || 'Falha ao salvar email.');
            }

            alert(json?.data?.message || 'Email salvo com sucesso.');
            elements.successModal.style.display = 'none';
        } catch (err) {
            console.error('Save email error:', err);
            alert(err?.message || 'Falha ao salvar email.');
        } finally {
            elements.saveEmailBtn.disabled = false;
            elements.saveEmailBtn.textContent = originalText;
        }
    }

    /**
     * Handle file selection
     */
    function handleFileSelect(e) {
        const files = Array.from(e.target.files);
        processFiles(files);
        e.target.value = ''; // Reset input
    }

    /**
     * Handle drag over
     */
    function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        elements.uploadDropzone.classList.add('dragover');
    }

    /**
     * Handle drag leave
     */
    function handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        elements.uploadDropzone.classList.remove('dragover');
    }

    /**
     * Handle drop
     */
    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        elements.uploadDropzone.classList.remove('dragover');

        const files = Array.from(e.dataTransfer.files).filter(file => file.type.startsWith('image/'));
        processFiles(files);
    }

    /**
     * Process uploaded files
     */
    /**
     * Process uploaded files
     */
    async function processFiles(files) {
        // Enforce strict limit
        const currentCount = state.photos.length;
        const remainingSlots = state.photoQuantity - currentCount;

        if (remainingSlots <= 0) {
            alert(sdppEditor.i18n.maxPhotos.replace('%d', state.photoQuantity));
            return;
        }

        // Filter valid types
        const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
        const validExts = ['.jpg', '.jpeg', '.png', '.webp', '.heic', '.heif'];
        const validFiles = files.filter(file => {
            const name = (file.name || '').toLowerCase();
            const type = (file.type || '').toLowerCase();

            // If browser provides a generic image/*, accept
            if (type.startsWith('image/')) return true;

            // Some mobile browsers provide empty/unknown MIME; fallback to extension
            if (!type) {
                return validExts.some(ext => name.endsWith(ext));
            }

            // Explicit allow-list (kept for safety)
            const isHeic = name.endsWith('.heic') || name.endsWith('.heif') || type === 'image/heic' || type === 'image/heif';
            if (isHeic) return true;
            return validTypes.includes(type);
        });

        if (validFiles.length < files.length) {
            alert('File format not supported. Please upload JPG, PNG, WebP or HEIC.');
        }

        if (validFiles.length === 0) return;

        // Check overflow for valid files
        let filesToProcess = validFiles;
        if (validFiles.length > remainingSlots) {
            alert('You have exceeded the maximum number of photos allowed for this order.');
            filesToProcess = validFiles.slice(0, remainingSlots);
        }

        // Always show loading on mobile; reading large images can be slow and looks like it "loaded only 2"
        if (elements.loadingOverlay && elements.loadingText) {
            elements.loadingOverlay.style.display = 'flex';
            elements.loadingText.textContent = 'Processando fotos...';
        }

        for (let i = 0; i < filesToProcess.length; i++) {
            const file = filesToProcess[i];
            try {
                let processedFile = file;
                let originalFile = null;

                if (elements.loadingText) {
                    elements.loadingText.textContent = `Processando fotos... (${i + 1}/${filesToProcess.length})`;
                }

                // Handle HEIC
                const lowerName = (file.name || '').toLowerCase();
                if (lowerName.endsWith('.heic') || lowerName.endsWith('.heif') || file.type === 'image/heic' || file.type === 'image/heif') {
                    if (typeof heic2any !== 'undefined') {
                        try {
                            const blob = await heic2any({
                                blob: file,
                                toType: 'image/png'
                            });
                            // Handle if result is array (shouldn't be for single file but safe check)
                            const resultBlob = Array.isArray(blob) ? blob[0] : blob;
                            originalFile = file;
                            processedFile = new File([resultBlob], file.name.replace(/\.(heic|heif)$/i, '.png'), {
                                type: 'image/png'
                            });
                        } catch (e) {
                            console.error('HEIC conversion failed', e);
                            alert(`Falha ao converter foto HEIC: ${file.name}. Tente converter para JPG antes de enviar.`);
                            continue;
                        }
                    } else {
                        console.warn('heic2any library not loaded');
                    }
                }

                // Use object URLs instead of base64 DataURL (much more stable on mobile)
                const objectUrl = URL.createObjectURL(processedFile);

                const photo = {
                    id: Date.now() + Math.random(),
                    file: processedFile,
                    originalFile,
                    objectUrl,
                    dataUrl: objectUrl,
                    text: '', // Legacy support
                    textLayers: [], // Text layers
                    emojiLayers: [], // Emoji layers (independent)
                    cropData: null,
                    zoom: 1,
                    rotation: 0
                };

                state.photos.push(photo);
                addThumbnail(photo);

                // Auto-select first photo immediately when it's added
                if (state.photos.length === 1 && state.currentPhotoIndex === -1) {
                    console.log('Auto-selecting first photo (inline)');
                    selectPhoto(0);
                }

                // Collapse upload section on mobile after photos are added
                if (elements.uploadSection && state.photos.length > 0) {
                    elements.uploadSection.classList.add('collapsed');
                }

            } catch (err) {
                console.error('Error processing file', err);
            }
        }

        if (elements.loadingOverlay) {
            elements.loadingOverlay.style.display = 'none';
        }

        // Always update UI to reflect new count
        updateUI();


    }

    /**
     * Add thumbnail to grid
     */
    function addThumbnail(photo) {
        const index = state.photos.indexOf(photo);
        const thumbnail = document.createElement('div');
        thumbnail.className = 'sdpp-thumbnail';
        thumbnail.dataset.photoId = String(photo.id);
        thumbnail.innerHTML = `
            <img src="${photo.dataUrl}" alt="Photo ${index + 1}">
            <div class="sdpp-thumbnail-checkbox" data-photo-id="${photo.id}"></div>
            <button type="button" class="sdpp-thumbnail-delete" data-photo-id="${photo.id}">×</button>
            <span class="sdpp-thumbnail-number">${index + 1}</span>
        `;

        // Click to select
        thumbnail.addEventListener('click', (e) => {
            // Prevent double-handling with the delegated click listener on the grid
            e.stopPropagation();

            // Mobile ghost/double click guard
            const now = Date.now();
            if (now - (state.lastThumbSelectAt || 0) < 350) {
                e.preventDefault();
                return;
            }
            state.lastThumbSelectAt = now;

            if (!e.target.classList.contains('sdpp-thumbnail-checkbox') &&
                !e.target.classList.contains('sdpp-thumbnail-delete')) {
                const id = String(thumbnail.dataset.photoId || '');
                const liveIndex = state.photos.findIndex(p => String(p.id) === id);
                if (liveIndex !== -1) {
                    selectPhoto(liveIndex);
                }
            }
        });

        // Checkbox toggle
        thumbnail.querySelector('.sdpp-thumbnail-checkbox').addEventListener('click', (e) => {
            e.stopPropagation();
            togglePhotoSelection(photo.id, e.target);
        });

        // Delete button
        thumbnail.querySelector('.sdpp-thumbnail-delete').addEventListener('click', (e) => {
            e.stopPropagation();
            deletePhoto(photo.id);
        });

        elements.thumbnailsGrid.appendChild(thumbnail);
    }

    function navigatePhoto(direction) {
        if (!state.photos || state.photos.length === 0) return;

        // Mobile: prevent double-tap causing skip
        const now = Date.now();
        if (now - (state.lastNavigateAt || 0) < 180) {
            return;
        }
        state.lastNavigateAt = now;

        if (state.currentPhotoIndex === -1) {
            selectPhoto(0);
            return;
        }
        const nextIndex = state.currentPhotoIndex + (direction || 0);
        if (nextIndex < 0 || nextIndex >= state.photos.length) return;

        // ── Navigation transition animation ──
        const frame = document.querySelector('.sdpp-polaroid-frame');
        const navOverlay = document.getElementById('nav-loading-overlay');
        if (!frame) {
            selectPhoto(nextIndex);
            return;
        }

        // Pre-save current edits before animating away
        if (state.cropper) {
            saveCropData();
        }

        // Store direction for the enter animation
        state._navDirection = direction;

        // Phase 1: fade-out + slide in the direction of navigation
        const slideOutClass = direction > 0 ? 'sdpp-slide-left' : 'sdpp-slide-right';
        frame.classList.add('sdpp-transitioning', slideOutClass);
        if (navOverlay) navOverlay.classList.add('active');

        // After fade-out animation completes (220ms matches CSS transition)
        setTimeout(() => {
            // Phase 2: set up enter position (from opposite side)
            frame.classList.remove(slideOutClass);
            const slideEnterClass = direction > 0 ? 'sdpp-slide-enter-left' : 'sdpp-slide-enter-right';
            frame.classList.add(slideEnterClass);

            // Load the new photo (this triggers loadImageInCropper)
            selectPhoto(nextIndex);

            // Phase 3: after a frame, remove enter class → CSS transitions slide it in
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    frame.classList.remove('sdpp-transitioning', slideEnterClass);
                    // Spinner will be hidden when cropper is ready (see ready callback)
                });
            });
        }, 220);
    }

    /**
     * Toggle photo selection for bulk actions
     */
    function togglePhotoSelection(photoId, checkbox) {
        if (state.selectedPhotos.has(photoId)) {
            state.selectedPhotos.delete(photoId);
            checkbox.classList.remove('checked');
            checkbox.textContent = '';
        } else {
            state.selectedPhotos.add(photoId);
            checkbox.classList.add('checked');
            checkbox.textContent = '✓';
        }
        updateBulkActionsVisibility();
    }

    /**
     * Toggle select all
     */
    function toggleSelectAll() {
        const allSelected = state.selectedPhotos.size === state.photos.length;

        if (allSelected) {
            state.selectedPhotos.clear();
        } else {
            state.photos.forEach(p => state.selectedPhotos.add(p.id));
        }

        updateCheckboxes();
        updateBulkActionsVisibility();
    }

    /**
     * Update checkboxes visual state
     */
    function updateCheckboxes() {
        document.querySelectorAll('.sdpp-thumbnail-checkbox').forEach(checkbox => {
            const photoId = String(checkbox.dataset.photoId || '');
            // state.selectedPhotos stores numeric IDs; compare as strings
            const isSelected = Array.from(state.selectedPhotos).some(id => String(id) === photoId);
            if (isSelected) {
                checkbox.classList.add('checked');
                checkbox.textContent = '✓';
            } else {
                checkbox.classList.remove('checked');
                checkbox.textContent = '';
            }
        });
    }

    function getCurrentAspectRatio() {
        let aspectRatio = 25 / 32; // Default borderless (3x3)
        if (state.hasBorder) {
            if (state.gridType === '2x3') {
                aspectRatio = 72 / 67;
            } else {
                aspectRatio = 634 / 710;
            }
        } else {
            if (state.gridType === '2x3') {
                aspectRatio = 921 / 1119;
            }
        }
        return aspectRatio;
    }

    function scheduleFinalPreviewUpdate() {
        if (state.isSubmitting) return;
        if (!elements.finalPreview || !elements.finalPreviewImage) return;
        if (state.finalPreviewTimer) {
            clearTimeout(state.finalPreviewTimer);
        }
        state.finalPreviewTimer = setTimeout(() => {
            state.finalPreviewTimer = null;
            updateFinalPreviewNow();
        }, 140);
    }

    function scheduleCropperStateSave() {
        if (state.isSubmitting) return;
        if (state.cropperStateSaveTimer) return;
        state.cropperStateSaveTimer = setTimeout(() => {
            state.cropperStateSaveTimer = null;
            saveCropData();
        }, 120);
    }

    function saveCropData() {
        try {
            if (state.isSubmitting) return;
            if (!state.cropper) return;
            const photo = state.photos[state.currentPhotoIndex];
            if (!photo) return;

            // Persist cropper state for pixel-perfect export + stable reload
            photo.cropData = state.cropper.getData(true);
            photo.canvasData = state.cropper.getCanvasData();
            photo.containerData = state.cropper.getContainerData();

            // Persist zoom even when the slider is hidden/removed
            const liveZoom = getCropperZoomRatio();
            if (liveZoom !== null && isFinite(liveZoom)) {
                photo.zoom = liveZoom;
            } else if (elements.zoomSlider) {
                const z = parseFloat(elements.zoomSlider.value);
                if (isFinite(z)) photo.zoom = z;
            }
        } catch (e) {
            console.error('saveCropData failed', e);
        }
    }

    function scheduleGestureSaveCropData() {
        try {
            if (state.isSubmitting) return;
            if (state.cropperGestureSaveTimer) {
                clearTimeout(state.cropperGestureSaveTimer);
            }
            state.cropperGestureSaveTimer = setTimeout(() => {
                state.cropperGestureSaveTimer = null;
                saveCropData();
            }, 120);
        } catch (e) {
        }
    }

    function getEditorRenderSize(quality) {
        // Match the PNG slot for bordered 3x3 to guarantee pixel-perfect framing.
        // For interaction, use a smaller buffer to keep mobile smooth.
        const q = quality || 'high';
        if (state.hasBorder && state.gridType !== '2x3') {
            return (q === 'low') ? { w: 316, h: 355 } : { w: 634, h: 710 };
        }
        // Default fallback used previously
        const w = (q === 'low') ? 260 : 520;
        const aspectRatio = getCurrentAspectRatio();
        return { w, h: Math.round(w / aspectRatio) };
    }

    function setImgSrcWithObjectUrl(imgEl, blob, onDone) {
        try {
            const url = URL.createObjectURL(blob);
            const prevUrl = imgEl.dataset.objectUrl;
            if (prevUrl) {
                try { URL.revokeObjectURL(prevUrl); } catch (e) { }
            }
            imgEl.dataset.objectUrl = url;
            const prevOnload = imgEl.onload;
            imgEl.onload = () => {
                if (typeof prevOnload === 'function') prevOnload();
                if (typeof onDone === 'function') onDone();
            };
            imgEl.src = url;
        } catch (e) {
            // fallback handled by caller
        }
    }

    function renderEditorOverlayNow(quality, onRendered) {
        try {
            if (state.isSubmitting) return;
            const activeEl = getActiveEditorOverlayEl();
            const targetEl = getInactiveEditorOverlayEl();
            if (!activeEl || !targetEl || activeEl.offsetParent === null) return;
            if (!state.cropper || !state.photos[state.currentPhotoIndex]) return;

            const size = getEditorRenderSize(quality);
            const canvas = state.cropper.getCroppedCanvas({
                width: size.w,
                height: size.h,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });
            if (!canvas) return;

            const swapIn = () => {
                // Swap visibility only after the new image has loaded
                if (elements.editorFinalImageA && elements.editorFinalImageB) {
                    activeEl.style.opacity = '0';
                    targetEl.style.opacity = '1';
                    state.editorOverlayActiveKey = (state.editorOverlayActiveKey === 'A') ? 'B' : 'A';
                } else {
                    targetEl.style.opacity = '1';
                }
                if (typeof onRendered === 'function') onRendered();
            };

            if (canvas.toBlob) {
                canvas.toBlob((blob) => {
                    if (!blob) {
                        const prevOnload = targetEl.onload;
                        targetEl.onload = () => {
                            if (typeof prevOnload === 'function') prevOnload();
                            swapIn();
                        };
                        targetEl.src = canvas.toDataURL('image/png');
                        return;
                    }
                    setImgSrcWithObjectUrl(targetEl, blob, swapIn);
                }, 'image/png');
            } else {
                const prevOnload = targetEl.onload;
                targetEl.onload = () => {
                    if (typeof prevOnload === 'function') prevOnload();
                    swapIn();
                };
                targetEl.src = canvas.toDataURL('image/png');
            }
        } catch (e) {
        }
    }

    function scheduleEditorOverlayRender(quality) {
        if (state.isSubmitting) return;
        if (!getActiveEditorOverlayEl()) return;
        if (state.editorOverlayTimer) return;
        const delay = (quality === 'low') ? 80 : 0;
        state.editorOverlayTimer = setTimeout(() => {
            state.editorOverlayTimer = null;
            renderEditorOverlayNow(quality);
        }, delay);
    }

    function setEditorOverlayVisible(isVisible) {
        const activeEl = getActiveEditorOverlayEl();
        const inactiveEl = getInactiveEditorOverlayEl();
        if (!activeEl) return;
        if (!isVisible) {
            activeEl.style.opacity = '0';
            if (inactiveEl && inactiveEl !== activeEl) inactiveEl.style.opacity = '0';
            return;
        }
        activeEl.style.opacity = '1';
        if (inactiveEl && inactiveEl !== activeEl) inactiveEl.style.opacity = '0';
    }

    function markGestureStart() {
        try {
            if (!state.cropper) return;
            state.gestureStartData = state.cropper.getData(true);
            state.gestureStartCanvas = state.cropper.getCanvasData();
        } catch (e) {
            state.gestureStartData = null;
            state.gestureStartCanvas = null;
        }
    }

    function hasGestureMovement() {
        try {
            if (!state.cropper || !state.gestureStartData || !state.gestureStartCanvas) return false;
            const d0 = state.gestureStartData;
            const c0 = state.gestureStartCanvas;
            const d1 = state.cropper.getData(true);
            const c1 = state.cropper.getCanvasData();
            if (!d1 || !c1) return false;

            // Small epsilon avoids treating tap as movement (mobile jitter)
            const eps = 0.6;
            const canvasMoved = (Math.abs((c1.left || 0) - (c0.left || 0)) > eps) ||
                (Math.abs((c1.top || 0) - (c0.top || 0)) > eps) ||
                (Math.abs((c1.width || 0) - (c0.width || 0)) > eps) ||
                (Math.abs((c1.height || 0) - (c0.height || 0)) > eps);

            const dataMoved = (Math.abs((d1.x || 0) - (d0.x || 0)) > eps) ||
                (Math.abs((d1.y || 0) - (d0.y || 0)) > eps) ||
                (Math.abs((d1.width || 0) - (d0.width || 0)) > eps) ||
                (Math.abs((d1.height || 0) - (d0.height || 0)) > eps);

            return canvasMoved || dataMoved;
        } catch (e) {
            return false;
        }
    }

    function updateFinalPreviewNow() {
        try {
            if (state.isSubmitting) return;
            if (!elements.finalPreview || !elements.finalPreviewImage) return;
            if (!state.cropper || !state.photos[state.currentPhotoIndex]) return;

            // If the final render is not visible/used, skip heavy canvas work (mobile performance)
            const finalPreviewHidden = (elements.finalPreview && elements.finalPreview.offsetParent === null);
            const editorFinalHidden = (!elements.editorFinalImage || elements.editorFinalImage.offsetParent === null);
            if (finalPreviewHidden && editorFinalHidden) return;

            const aspectRatio = getCurrentAspectRatio();
            const exportW = 520;
            const exportH = Math.round(exportW / aspectRatio);
            const canvas = state.cropper.getCroppedCanvas({
                width: exportW,
                height: exportH,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });
            if (!canvas) return;
            const url = canvas.toDataURL('image/png');
            elements.finalPreviewImage.src = url;
            // editor overlay is handled separately for performance/precision
            // Do not force-show the bottom preview; the main editor now uses the final render.
        } catch (e) {
        }
    }

    function updateCurrentPreviewThumb() {
        try {
            if (state.isSubmitting) return;
            if (!state.cropper || !state.photos[state.currentPhotoIndex]) return;
            const photo = state.photos[state.currentPhotoIndex];
            const aspectRatio = getCurrentAspectRatio();
            const exportW = 600;
            const exportH = Math.round(exportW / aspectRatio);
            const canvas = state.cropper.getCroppedCanvas({
                width: exportW,
                height: exportH,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });
            if (!canvas) return;
            photo.previewThumb = canvas.toDataURL('image/png');
            scheduleFinalPreviewUpdate();
        } catch (e) {
        }
    }

    function generatePreviewThumbForPhoto(photo, timeoutMs = 5000) {
        return new Promise((resolve) => {
            try {
                if (!photo || !photo.dataUrl) {
                    resolve(null);
                    return;
                }

                const aspectRatio = getCurrentAspectRatio();
                const exportW = 600;
                const exportH = Math.round(exportW / aspectRatio);

                const img = new Image();
                let done = false;

                const t = setTimeout(() => {
                    if (done) return;
                    done = true;
                    resolve(null);
                }, Math.max(1000, timeoutMs || 0));

                img.onload = () => {
                    if (done) return;
                    const container = document.createElement('div');
                    container.style.position = 'fixed';
                    container.style.left = '-10000px';
                    container.style.top = '-10000px';
                    const cw = Math.max(1, Math.round(photo.containerData?.width || 400));
                    const ch = Math.max(1, Math.round(photo.containerData?.height || 400));
                    container.style.width = `${cw}px`;
                    container.style.height = `${ch}px`;
                    container.style.overflow = 'hidden';
                    container.style.opacity = '0';
                    container.style.pointerEvents = 'none';
                    img.style.display = 'block';
                    img.style.maxWidth = 'none';
                    img.style.maxHeight = 'none';
                    container.appendChild(img);
                    document.body.appendChild(container);

                    let tmpCropper;
                    const cleanup = () => {
                        try {
                            if (tmpCropper) tmpCropper.destroy();
                        } catch (e) {
                        }
                        try {
                            container.remove();
                        } catch (e) {
                        }
                    };

                    setTimeout(() => {
                        try {
                            tmpCropper = new Cropper(img, {
                                aspectRatio: aspectRatio,
                                viewMode: 1,
                                dragMode: 'move',
                                autoCropArea: 1,
                                cropBoxMovable: false,
                                cropBoxResizable: false,
                                toggleDragModeOnDblclick: false,
                                guides: false,
                                center: false,
                                highlight: false,
                                background: false,
                                modal: false,
                                ready: () => {
                                    try {
                                        // IMPORTANT: canvasData depends on container size.
                                        // We recreate the container with the original containerData dims to avoid drift.
                                        if (photo.canvasData) {
                                            tmpCropper.setCanvasData(photo.canvasData);
                                        }
                                        if (photo.cropData) {
                                            tmpCropper.setData(photo.cropData);
                                        }
                                        if (photo.rotation) {
                                            tmpCropper.rotateTo(photo.rotation);
                                        }

                                        const canvas = tmpCropper.getCroppedCanvas({
                                            width: exportW,
                                            height: exportH,
                                            imageSmoothingEnabled: true,
                                            imageSmoothingQuality: 'high'
                                        });

                                        const url = canvas ? canvas.toDataURL('image/png') : null;
                                        if (!done) {
                                            done = true;
                                            clearTimeout(t);
                                            cleanup();
                                            resolve(url);
                                        }
                                    } catch (e) {
                                        if (!done) {
                                            done = true;
                                            clearTimeout(t);
                                            cleanup();
                                            resolve(null);
                                        }
                                    }
                                }
                            });
                        } catch (e) {
                            if (!done) {
                                done = true;
                                clearTimeout(t);
                                cleanup();
                                resolve(null);
                            }
                        }
                    }, 0);
                };

                img.onerror = () => {
                    if (done) return;
                    done = true;
                    clearTimeout(t);
                    resolve(null);
                };

                img.src = photo.dataUrl;
            } catch (e) {
                resolve(null);
            }
        });
    }

    /**
     * Update bulk actions visibility
     */
    function updateBulkActionsVisibility() {
        const hasPhotos = state.photos.length > 0;
        const allSelected = state.selectedPhotos.size === state.photos.length && hasPhotos;

        // Show/hide bulk actions (delete, duplicate, etc.)
        elements.bulkActions.style.display = state.selectedPhotos.size > 0 ? 'block' : 'none';

        // Show/hide select all button
        elements.selectAllBtn.style.display = hasPhotos ? 'block' : 'none';

        // Always update button text
        elements.selectAllBtn.textContent = allSelected ? sdppEditor.i18n.deselectAll : sdppEditor.i18n.selectAll;
    }

    /**
     * Delete selected photos
     */
    function deleteSelectedPhotos() {
        if (!confirm(sdppEditor.i18n.deleteSelectedConfirm.replace('%d', state.selectedPhotos.size))) return;

        state.selectedPhotos.forEach(photoId => {
            deletePhoto(photoId, false);
        });

        state.selectedPhotos.clear();
        rebuildThumbnails();
        updateUI();

        if (state.photos.length > 0) {
            selectPhoto(Math.min(state.currentPhotoIndex, state.photos.length - 1));
        }
    }

    /**
     * Duplicate selected photos
     */
    function duplicateSelectedPhotos() {
        if (state.selectedPhotos.size === 0) return;

        const currentCount = state.photos.length;
        const availableSlots = state.photoQuantity - currentCount;

        if (availableSlots <= 0) {
            alert(sdppEditor.i18n.maxPhotos.replace('%d', state.photoQuantity));
            return;
        }

        const toDuplicate = Array.from(state.selectedPhotos);
        let duplicatedCount = 0;

        // Limit duplication to available slots
        const limitedDuplication = toDuplicate.slice(0, availableSlots);

        limitedDuplication.forEach(photoId => {
            const originalPhoto = state.photos.find(p => p.id === photoId);
            if (originalPhoto) {
                // deep copy photo object
                const duplicate = JSON.parse(JSON.stringify(originalPhoto));

                // Assign new unique ID
                duplicate.id = Date.now() + Math.random();

                // Maintain the same dataUrl (it's a string, so JSON copy is fine)
                // Note: file object might be lost in JSON stringify/parse, 
                // but since we keep the dataUrl and other properties, it should be fine for the editor.
                // Re-assigning file if possible (reference copy is fine as file is immutable blob-like)
                duplicate.file = originalPhoto.file;

                // Bug 11 fix: objectUrl is a blob URL that JSON.stringify drops
                duplicate.objectUrl = originalPhoto.objectUrl;

                state.photos.push(duplicate);
                duplicatedCount++;
            }
        });

        if (duplicatedCount < toDuplicate.length) {
            alert(sdppEditor.i18n.partialDuplicationLimit || `Some photos were not duplicated because the limit of ${state.photoQuantity} photos was reached.`);
        }

        rebuildThumbnails();
        updateUI();

        // Clear selection after duplication to avoid confusion
        state.selectedPhotos.clear();
        updateCheckboxes();
        updateBulkActionsVisibility();

        // Select the last duplicated photo
        if (duplicatedCount > 0) {
            selectPhoto(state.photos.length - 1);
        }
    }

    /**
     * Apply style to selected photos
     */
    function applyStyleToSelected() {
        if (state.selectedPhotos.size < 2) {
            alert(sdppEditor.i18n.selectMorePhotos || 'Select at least 2 photos to apply styles.');
            return;
        }

        const selectedIndices = Array.from(state.selectedPhotos).sort((a, b) => {
            const indexA = state.photos.findIndex(p => p.id === a);
            const indexB = state.photos.findIndex(p => p.id === b);
            return indexA - indexB;
        });

        // Determined Source Logic:
        // "The system must always use the first edited photo as the source of the style"
        // Interpretation: If I am currently editing Photo X and it is selected, Photo X is the source.
        // If I am not editing a selected photo, use the first selected one.

        let refPhotoId = null;
        const currentPhotoId = state.photos[state.currentPhotoIndex].id;

        if (state.selectedPhotos.has(currentPhotoId)) {
            refPhotoId = currentPhotoId; // Prioritize current if selected
        } else {
            refPhotoId = selectedIndices[0]; // Fallback to first selected
        }

        const refPhoto = state.photos.find(p => p.id === refPhotoId);

        if (!refPhoto || !refPhoto.textLayers) {
            alert(sdppEditor.i18n.noTextToApply || 'Selection has no text style to apply.');
            return;
        }

        // Apply to others
        const targets = Array.from(state.selectedPhotos).filter(id => id !== refPhotoId);
        let appliedCount = 0;

        targets.forEach(photoId => {
            const photo = state.photos.find(p => p.id === photoId);
            if (photo) {
                // Save state for each photo before applying style
                // We need to temporarily set currentPhotoIndex to save correct state
                const originalIndex = state.currentPhotoIndex;
                const targetIndex = state.photos.findIndex(p => p.id === photoId);
                if (targetIndex !== -1) {
                    state.currentPhotoIndex = targetIndex;
                    historyManager.saveState();
                    state.currentPhotoIndex = originalIndex;
                }

                // deep copy text layers
                // Use JSON parse/stringify to copy efficiently
                photo.textLayers = JSON.parse(JSON.stringify(refPhoto.textLayers));

                // Copy Emoji Layers
                if (refPhoto.emojiLayers) {
                    photo.emojiLayers = JSON.parse(JSON.stringify(refPhoto.emojiLayers));
                } else {
                    photo.emojiLayers = [];
                }

                appliedCount++;
            }
        });

        alert((sdppEditor.i18n.styleApplied || 'Style applied to %d photos.').replace('%d', appliedCount));

        // If current photo is in the target list (was not the source but was selected), refresh overlay
        if (state.selectedPhotos.has(state.photos[state.currentPhotoIndex].id) && state.photos[state.currentPhotoIndex].id !== refPhotoId) {
            // Re-render only if current photo is affected
            if (state.hasBorder) {
                textManager.renderOverlay();
                textManager.renderControls(true);
                if (emojiManager) {
                    emojiManager.renderOverlay();
                    emojiManager.renderControls(true);
                }
            }
        }
    }

    /**
     * Delete single photo
     */
    function deletePhoto(photoId, confirmDelete = true) {
        if (confirmDelete && !confirm(sdppEditor.i18n.deletePhotoConfirm)) return;

        const index = state.photos.findIndex(p => p.id === photoId);
        if (index === -1) return;

        state.photos.splice(index, 1);
        state.selectedPhotos.delete(photoId);

        if (confirmDelete) {
            rebuildThumbnails();
            updateUI();

            if (state.photos.length > 0) {
                selectPhoto(Math.min(state.currentPhotoIndex, state.photos.length - 1));
            }
        }
    }

    /**
     * Rebuild thumbnails grid
     */
    function rebuildThumbnails() {
        elements.thumbnailsGrid.innerHTML = '';
        state.photos.forEach(photo => addThumbnail(photo));
        updateBulkActionsVisibility();
    }

    /**
     * Select photo for editing
     */
    function selectPhoto(index) {
        try {
            if (index < 0 || index >= state.photos.length) return;

            // If clicking on the same photo that's already selected, do nothing
            if (state.currentPhotoIndex === index) {
                return;
            }

            // Save current crop data before switching
            if (state.cropper) {
                saveCropData();
            }

            state.currentPhotoIndex = index;
            const photo = state.photos[index];

            // Ensure the base image is visible during switching (Cropper may take a moment to initialize)
            try {
                const wrap = elements.cropperImage ? elements.cropperImage.closest('.sdpp-cropper-wrapper') : null;
                if (wrap) wrap.classList.remove('sdpp-cropper-active');
            } catch (e) {
                // ignore
            }

            // Prevent showing the previous photo's final render while the new one loads
            try {
                setEditorOverlayVisible(false);
                if (elements.editorFinalImageA) elements.editorFinalImageA.src = '';
                if (elements.editorFinalImageB) elements.editorFinalImageB.src = '';
                if (elements.editorFinalImage) elements.editorFinalImage.src = '';
            } catch (e) {
                // ignore
            }

            // Update thumbnail active state
            document.querySelectorAll('.sdpp-thumbnail').forEach((thumb, i) => {
                thumb.classList.toggle('active', i === index);
            });

            // Show editor, hide empty state
            elements.emptyState.style.display = 'none';
            elements.photoEditor.style.display = 'flex';
            elements.gridPreview.style.display = 'none';

            // Update labels
            try {
                const labelTemplate = (typeof sdppEditor !== 'undefined' && sdppEditor && sdppEditor.i18n && sdppEditor.i18n.photoLabel)
                    ? sdppEditor.i18n.photoLabel
                    : 'Foto %d';
                elements.currentPhotoLabel.textContent = String(labelTemplate).replace('%d', index + 1);
            } catch (e) {
                elements.currentPhotoLabel.textContent = `Foto ${index + 1}`;
            }
            elements.currentIndex.textContent = index + 1;
            elements.totalPhotos.textContent = state.photos.length;

            // Update navigation buttons
            elements.prevPhotoBtn.disabled = index === 0;
            elements.nextPhotoBtn.disabled = index === state.photos.length - 1;

            // Load image in cropper (tokenized to avoid async races when navigating fast)
            const loadToken = ++state.cropperLoadToken;
            loadImageInCropper(photo, loadToken);

            // Update Text Manager
            if (state.hasBorder) {
                // Convert legacy text to text layer if needed
                if (photo.text && (!photo.textLayers || photo.textLayers.length === 0)) {
                    photo.textLayers = [{
                        text: photo.text,
                        fontFamily: photo.fontFamily || 'Pacifico',
                        color: '#000000',
                        size: 28,
                        bold: false,
                        italic: false,
                        x: 0,
                        y: 0
                    }];
                }
                if (!photo.textLayers) photo.textLayers = [];

                textManager.activeLayerIndex = -1; // Deselect layers
                textManager.renderOverlay();
                textManager.renderControls();

                // Update Emoji Manager
                if (emojiManager) {
                    if (!photo.emojiLayers) photo.emojiLayers = [];
                    emojiManager.activeLayerIndex = -1;
                    emojiManager.renderOverlay();
                    emojiManager.renderControls();
                }
            }

            // Update undo/redo button states for the new photo
            historyManager.updateButtons();
        } catch (e) {
            console.error('selectPhoto failed', e);
        }
    }

    /**
     * Load image in Cropper.js
     */
    /**
     * Load image in Cropper.js
     */
    function loadImageInCropper(photo) {
        const expectedToken = arguments.length > 1 ? arguments[1] : state.cropperLoadToken;

        // Destroy existing cropper
        if (state.cropper) {
            state.cropper.destroy();
            state.cropper = null;
        }

        state.cropperReady = false;

        // Make sure the base image can be seen while Cropper is being created
        try {
            const wrap = elements.cropperImage ? elements.cropperImage.closest('.sdpp-cropper-wrapper') : null;
            if (wrap) wrap.classList.remove('sdpp-cropper-active');
        } catch (e) {
            // ignore
        }

        // Detach previous handlers
        try {
            if (elements.cropperImage) {
                elements.cropperImage.onload = null;
                elements.cropperImage.onerror = null;

                // Cropper may leave the source image hidden; ensure it's visible during switching/loading
                elements.cropperImage.classList.remove('cropper-hidden');
                elements.cropperImage.style.display = '';
                elements.cropperImage.style.opacity = '';
            }
        } catch (e) {
            // ignore
        }

        // Use stable per-photo URL (objectUrl created once on upload)
        const src = photo?.objectUrl || photo?.dataUrl || photo?.image_url || photo?.url || '';
        if (!src) {
            console.error('No image src available for cropper photo', photo);
            return;
        }

        let cropperInitDone = false;
        const expectedPhotoId = String(photo?.id || '');
        const isStale = () => {
            if (expectedToken !== state.cropperLoadToken) return true;
            const current = state.photos[state.currentPhotoIndex];
            if (!current) return true;
            const currentId = String(current.id || '');
            return currentId !== expectedPhotoId;
        };
        const handleImageLoaded = () => {
            if (cropperInitDone) return;
            cropperInitDone = true;

            // If user navigated while this load was in-flight, ignore stale callbacks
            if (isStale()) return;

            // Determine aspect ratio
            let aspectRatio = 25 / 32; // Default borderless (3x3)
            if (state.hasBorder) {
                if (state.gridType === '2x3') {
                    aspectRatio = 72 / 67; // approx 1.07
                } else {
                    aspectRatio = 634 / 710; // 3x3 bordered: matches print slot 634x710px
                }
            } else {
                // Borderless
                if (state.gridType === '2x3') {
                    aspectRatio = 921 / 1119;
                }
            }

            state.cropper = new Cropper(elements.cropperImage, {
                aspectRatio: aspectRatio,
                // Allow zoom-out and showing empty spaces around the image
                viewMode: 0,
                dragMode: 'none',
                autoCropArea: 1,
                zoomOnTouch: false,
                zoomOnWheel: false,
                cropBoxMovable: false,
                cropBoxResizable: false,
                toggleDragModeOnDblclick: false,
                guides: false,
                center: false,
                highlight: false,
                background: false,
                modal: false, // This removes the dark overlay around crop box
                cropstart: () => {
                    if (state.isProgrammaticTransform) return;
                    state.editorOverlayHiddenDuringGesture = false;
                    markGestureStart();
                },
                crop: () => {
                    if (state.isProgrammaticTransform) return;
                    // Only hide when there is real movement
                    if (!state.editorOverlayHiddenDuringGesture && hasGestureMovement()) {
                        state.editorOverlayHiddenDuringGesture = true;
                        setEditorOverlayVisible(false);
                    }
                },
                cropend: () => {
                    if (state.isProgrammaticTransform) return;
                    // Ensure the final visual framing is persisted (mobile can lag otherwise)
                    requestAnimationFrame(() => {
                        saveCropData();
                        // Keep it hidden until the new high render has loaded to avoid flicker
                        renderEditorOverlayNow('high', () => {
                            setEditorOverlayVisible(true);
                        });
                    });
                    state.editorOverlayHiddenDuringGesture = false;
                },
                zoomstart: () => {
                    if (state.isProgrammaticTransform) return;
                    state.editorOverlayHiddenDuringGesture = false;
                    markGestureStart();
                    // Zoom gesture should never flicker: hide immediately
                    setEditorOverlayVisible(false);
                    state.editorOverlayHiddenDuringGesture = true;
                },
                zoom: (e) => {
                    if (state.isProgrammaticTransform) return;
                    // Keep zoom + canvasData synchronized with what user is seeing
                    const ratio = e?.detail?.ratio;
                    if (typeof ratio === 'number' && isFinite(ratio)) {
                        elements.zoomSlider.value = ratio;
                        const p = state.photos[state.currentPhotoIndex];
                        if (p) {
                            p.zoom = ratio;
                        }
                    }
                    scheduleCropperStateSave();
                    if (!state.editorOverlayHiddenDuringGesture && hasGestureMovement()) {
                        state.editorOverlayHiddenDuringGesture = true;
                        setEditorOverlayVisible(false);
                    }
                },
                zoomend: () => {
                    if (state.isProgrammaticTransform) return;
                    if (state.cropperStateSaveTimer) {
                        clearTimeout(state.cropperStateSaveTimer);
                        state.cropperStateSaveTimer = null;
                    }
                    requestAnimationFrame(() => {
                        saveCropData();
                        renderEditorOverlayNow('high', () => {
                            setEditorOverlayVisible(true);
                        });
                    });
                    state.editorOverlayHiddenDuringGesture = false;
                },
                ready: () => {
                    if (isStale()) return;
                    state.cropperReady = true;

                    // Cropper is now active; hide the base <img> so only cropper canvas is seen
                    try {
                        const wrap = elements.cropperImage ? elements.cropperImage.closest('.sdpp-cropper-wrapper') : null;
                        if (wrap) wrap.classList.add('sdpp-cropper-active');
                    } catch (e) {
                        // ignore
                    }

                    // Restore previous crop data if exists
                    // Order matters: Rotate -> Zoom -> (Canvas/Crop)
                    if (photo.rotation) {
                        state.cropper.rotateTo(photo.rotation);
                    }

                    if (photo.zoom) {
                        state.cropper.zoomTo(photo.zoom);
                    }

                    if (photo.canvasData) {
                        state.cropper.setCanvasData(photo.canvasData);
                    }

                    if (photo.cropData) {
                        state.cropper.setData(photo.cropData);
                    }

                    // Update zoom slider
                    // Sync slider with actual zoom
                    const canvasData = state.cropper.getCanvasData();
                    const containerData = state.cropper.getContainerData();
                    if (canvasData && containerData && canvasData.width) {
                        const zoomRatio = canvasData.width / (state.cropper.getImageData().naturalWidth || canvasData.width); // This might be tricky
                        // Provide a simplified zoom tracking via stored property or fallback
                        elements.zoomSlider.value = photo.zoom || 1;
                    }

                    // Ensure the editor always shows the exact final render after load
                    renderEditorOverlayNow('high', () => {
                        setEditorOverlayVisible(true);
                    });

                    if (state.pendingZoom !== null && isFinite(state.pendingZoom)) {
                        const pending = state.pendingZoom;
                        state.pendingZoom = null;
                        elements.zoomSlider.value = pending;
                        state.cropper.zoomTo(pending);
                        saveCropData();
                    } else if (!photo.cropData && !photo.canvasData && !photo.zoom) {
                        // NEW behavior: Fit image to container initially
                        try {
                            const containerData = state.cropper.getContainerData();
                            const imageData = state.cropper.getImageData();
                            if (containerData && imageData && imageData.naturalWidth && imageData.naturalHeight) {
                                // Calculate scale needed to fit width and height
                                const scaleW = containerData.width / imageData.naturalWidth;
                                const scaleH = containerData.height / imageData.naturalHeight;
                                // Use the smaller scale to ensure it fits entirely
                                const fitZoom = Math.min(scaleW, scaleH);

                                // Apply zoom - slightly reduce to ensure margins if desired, or use exact fit
                                state.cropper.zoomTo(fitZoom);

                                // Center the image manually after zoom
                                const newCanvasData = state.cropper.getCanvasData();
                                state.cropper.setCanvasData({
                                    left: (containerData.width - newCanvasData.width) / 2,
                                    top: (containerData.height - newCanvasData.height) / 2
                                });

                                // Update slider to match this new automatic "fit" zoom
                                if (elements.zoomSlider) {
                                    elements.zoomSlider.value = fitZoom;
                                }
                                photo.zoom = fitZoom;
                                saveCropData();
                            }
                        } catch (e) {
                            console.error('Auto-fit failed', e);
                        }
                    }
                    state.editorOverlayHiddenDuringGesture = false;

                    // Dismiss navigation transition spinner
                    try {
                        const navOverlay = document.getElementById('nav-loading-overlay');
                        if (navOverlay) navOverlay.classList.remove('active');
                    } catch (e) { /* ignore */ }
                }
            });
        };

        elements.cropperImage.onload = handleImageLoaded;
        elements.cropperImage.onerror = () => {
            console.error('Failed to load image for cropper', { src, photo });
        };
        elements.cropperImage.src = src;

        // If image is cached, onload might not fire consistently in some mobile browsers
        if (elements.cropperImage.complete && elements.cropperImage.naturalWidth) {
            setTimeout(handleImageLoaded, 0);
        }

        // Extra safety: poll for naturalWidth (some devices/webviews fail to emit onload)
        const start = Date.now();
        const poll = () => {
            if (cropperInitDone) return;
            if (isStale()) return;
            const elapsed = Date.now() - start;
            if (elapsed > 3000) return;
            if (elements.cropperImage.complete && elements.cropperImage.naturalWidth) {
                handleImageLoaded();
                return;
            }
            setTimeout(poll, 60);
        };
        setTimeout(poll, 60);

        // Mobile/WebView fallback: decode() resolves even when onload is flaky with object URLs
        try {
            if (elements.cropperImage && typeof elements.cropperImage.decode === 'function') {
                elements.cropperImage.decode()
                    .then(() => {
                        if (cropperInitDone) return;
                        if (isStale()) return;
                        if (elements.cropperImage.naturalWidth) {
                            handleImageLoaded();
                        }
                    })
                    .catch(() => {
                        // ignore
                    });
            }
        } catch (e) {
            // ignore
        }

        // Last resort: if naturalWidth appears shortly after setting src, init.
        setTimeout(() => {
            if (cropperInitDone) return;
            if (isStale()) return;
            if (elements.cropperImage && elements.cropperImage.naturalWidth) {
                handleImageLoaded();
            }
        }, 150);
    }

    /**
     * Adjust zoom
     */
    function getCropperZoomRatio() {
        try {
            if (!state.cropper) return null;
            const canvasData = state.cropper.getCanvasData();
            const imageData = state.cropper.getImageData();
            const nw = imageData?.naturalWidth;
            const cw = canvasData?.width;
            if (!nw || !cw) return null;
            const ratio = cw / nw;
            return (typeof ratio === 'number' && isFinite(ratio) && ratio > 0) ? ratio : null;
        } catch (e) {
            return null;
        }
    }

    function adjustZoom(delta) {
        // Save state before change
        historyManager.saveState();

        const liveZoom = getCropperZoomRatio();
        const sliderValue = elements.zoomSlider ? parseFloat(elements.zoomSlider.value) : 1;
        const currentZoom = (liveZoom !== null) ? liveZoom : sliderValue;
        const minZoom = elements.zoomSlider ? parseFloat(elements.zoomSlider.min || '0.1') : 0.1;
        const maxZoom = elements.zoomSlider ? parseFloat(elements.zoomSlider.max || '3') : 3;
        const step = elements.zoomSlider ? parseFloat(elements.zoomSlider.step || '0.05') : 0.05;
        const appliedDelta = (typeof delta === 'number' && isFinite(delta)) ? delta : 0;

        // If delta is small (e.g. from click), use step as unit
        const next = currentZoom + appliedDelta;
        const snapped = Math.round(next / step) * step;
        const newZoom = Math.max(minZoom, Math.min(maxZoom, snapped));

        if (elements.zoomSlider) {
            elements.zoomSlider.value = newZoom;
        }

        showInteractionLoading();

        // If cropper isn't created yet, queue the zoom to be applied on ready
        if (!state.cropper) {
            state.pendingZoom = newZoom;
            setTimeout(hideInteractionLoading, 300);
            return;
        }

        if (!state.cropperReady) {
            state.pendingZoom = newZoom;
            setTimeout(hideInteractionLoading, 300);
            return;
        }

        state.isProgrammaticTransform = true;
        state.cropper.zoomTo(newZoom);
        setTimeout(() => {
            state.isProgrammaticTransform = false;
        }, 0);

        // Persist + refresh overlay so what the user sees matches export
        saveCropData();
        scheduleEditorOverlayRender('low');
        renderEditorOverlayNow('high', () => {
            hideInteractionLoading();
        });
    }

    function nudgeImage(dx, dy) {
        if (!state.cropper) return;

        showInteractionLoading();

        try {
            historyManager.saveState();
        } catch (e) {
            // ignore - should not block moving
        }

        try {
            const canvasData = state.cropper.getCanvasData();
            if (!canvasData || typeof canvasData.left !== 'number' || typeof canvasData.top !== 'number') {
                return;
            }

            // Use canvasData movement to work even with dragMode: 'none'
            state.isProgrammaticTransform = true;
            state.cropper.setCanvasData({
                ...canvasData,
                left: canvasData.left + dx,
                top: canvasData.top + dy
            });
            setTimeout(() => {
                state.isProgrammaticTransform = false;
            }, 0);
        } catch (e) {
            console.error('Failed to nudge image', e);
            hideInteractionLoading();
            return;
        }

        saveCropData();

        scheduleEditorOverlayRender('low');
        renderEditorOverlayNow('high', () => {
            hideInteractionLoading();
        });
    }

    /**
     * Handle zoom slider
     * Bug 10 fix: debounce history saves so dragging the slider doesn't
     * create dozens of undo entries. History is saved once per drag session.
     */
    function handleZoomSlider(e) {
        // Show loading
        showInteractionLoading();

        // Debounced history save: only save once per drag session (500ms idle)
        if (!state._zoomSliderHistorySaved) {
            state._zoomSliderHistorySaved = true;
            historyManager.saveState();
        }
        if (state._zoomSliderDebounce) {
            clearTimeout(state._zoomSliderDebounce);
        }
        state._zoomSliderDebounce = setTimeout(() => {
            state._zoomSliderHistorySaved = false;
            state._zoomSliderDebounce = null;
        }, 500);

        const z = parseFloat(e.target.value);
        if (!state.cropper) {
            state.pendingZoom = z;
            return;
        }
        if (!state.cropperReady) {
            state.pendingZoom = z;
            return;
        }
        state.isProgrammaticTransform = true;
        state.cropper.zoomTo(z);
        setTimeout(() => {
            state.isProgrammaticTransform = false;
        }, 0);

        // Persist + refresh overlay so what the user sees matches export
        saveCropData();
        scheduleEditorOverlayRender('low');
        renderEditorOverlayNow('high', () => {
            // For slider, we might want to debounce hiding slightly or hide immediately if drag ends
            // But simple hide here allows single clicks on slider track to work with feedback
            // For continuous drag, this may flash. Better to check if mouse is down?
            // Given user request "appears loading overlay", let's be safe.
            hideInteractionLoading();
        });
    }

    /**
     * Rotate image
     */
    function rotateImage(degrees) {
        if (!state.cropper) return;

        // Save state before change
        historyManager.saveState();

        state.cropper.rotate(degrees);

        // Save rotation
        const photo = state.photos[state.currentPhotoIndex];
        if (photo) {
            photo.rotation = (photo.rotation || 0) + degrees;
        }

        // Persist immediately so export matches what user sees
        saveCropData();
    }

    /**
     * Apply font to all photos (Modified for multi-layer support)
     */
    /**
     * Apply font to all photos (Modified for multi-layer support)
     */
    function applyFontToAll() {
        if (!state.photos[state.currentPhotoIndex]) return;

        // Requirements: "Only the text content and text styling must be applied to all photos... Image settings must NOT be copied."
        const currentLayers = state.photos[state.currentPhotoIndex].textLayers || [];

        if (currentLayers.length === 0) {
            alert(sdppEditor.i18n.addTextWarning || 'Adicione ao menos um texto para aplicar a todos.');
            return;
        }

        let targetIndices = [];

        // Check if there are selected photos (bulk action)
        if (state.selectedPhotos.size > 0) {
            targetIndices = Array.from(state.selectedPhotos);
            // Exclude current if selected
            targetIndices = targetIndices.filter(idx => idx !== state.currentPhotoIndex);

            if (targetIndices.length === 0) {
                alert(sdppEditor.i18n.noOtherSelected || 'No other photos selected to apply to.');
                return;
            }

            if (!confirm(sdppEditor.i18n.confirmApplySelected || 'Apply text styles to selected photos?')) {
                return;
            }
        } else {
            // Apply to ALL if nothing is selected
            if (!confirm(sdppEditor.i18n.confirmApplyAll || 'Apply text styles to ALL other photos? This will replace existing text.')) {
                return;
            }
            // All indices except current
            state.photos.forEach((_, idx) => {
                if (idx !== state.currentPhotoIndex) targetIndices.push(idx);
            });
        }

        // Apply
        targetIndices.forEach(index => {
            if (state.photos[index]) {
                // Save state for each photo before applying style
                const originalIndex = state.currentPhotoIndex;
                state.currentPhotoIndex = index;
                historyManager.saveState();
                state.currentPhotoIndex = originalIndex;

                // Deep copy of layers to avoid reference issues
                // We keep text positioning relative to the frame (which is constant)
                state.photos[index].textLayers = JSON.parse(JSON.stringify(currentLayers));

                // Also copy emoji layers
                const currentEmojis = state.photos[state.currentPhotoIndex].emojiLayers || [];
                state.photos[index].emojiLayers = JSON.parse(JSON.stringify(currentEmojis));
            }
        });

        alert(sdppEditor.i18n.applySuccess || 'Text styles applied successfully!');
    }

    /**
     * Show grid preview
     */
    function showGridPreview() {
        // Save crop data for current photo
        saveCropData();
        updateCurrentPreviewThumb();

        // Hide editor, show grid preview
        elements.photoEditor.style.display = 'none';
        elements.gridPreview.style.display = 'block';

        // Build grid
        elements.gridContainer.innerHTML = '';

        state.photos.forEach((photo, index) => {
            const item = document.createElement('div');
            item.className = 'sdpp-grid-item';

            const previewSrc = photo.previewThumb || photo.dataUrl;

            // Generate simple text preview for grid
            let textPreview = '';
            if (state.hasBorder && photo.textLayers && photo.textLayers.length > 0) {
                // Show only first text layer or a generic label for preview simplicity
                const firstLayer = photo.textLayers[0];
                textPreview = `<div class="sdpp-grid-item-text" style="font-family: '${firstLayer.fontFamily}', cursive">${firstLayer.text}</div>`;
            }

            item.innerHTML = `
                <div class="sdpp-grid-item-image">
                    <img data-preview-index="${index}" src="${previewSrc}" alt="Photo ${index + 1}">
                </div>
                ${textPreview}
            `;

            item.addEventListener('click', () => {
                selectPhoto(index);
            });

            elements.gridContainer.appendChild(item);
        });

        // Generate missing thumbs in background so ALL 9 tiles reflect the real export framing.
        state.photos.forEach((photo, index) => {
            if (photo.previewThumb) return;
            if (index === state.currentPhotoIndex && state.cropper) {
                updateCurrentPreviewThumb();
                return;
            }
            generatePreviewThumbForPhoto(photo).then((url) => {
                if (!url) return;
                photo.previewThumb = url;
                const img = elements.gridContainer.querySelector(`img[data-preview-index="${index}"]`);
                if (img) {
                    img.src = url;
                }
            });
        });
    }

    /**
     * Handle logout - clear cookie and redirect to login
     */
    function handleLogout() {
        if (confirm('Deseja sair deste pedido e voltar para a tela de login?')) {
            // Redirect with logout param to trigger server-side cookie deletion
            const url = new URL(window.location.href);
            url.searchParams.set('sdpp_logout', '1');
            window.location.href = url.toString();
        }
    }

    /**
     * Update UI state
     */
    function updateUI() {
        const photoCount = state.photos.length;

        // Update progress
        elements.photosUploaded.textContent = photoCount;
        const progress = (photoCount / state.photoQuantity) * 100;
        elements.progressFill.style.width = `${Math.min(100, progress)}%`;

        // Update navigation counter (e.g., "3 / 7")
        if (state.photos.length > 0) {
            elements.currentIndex.textContent = state.currentPhotoIndex + 1;
            elements.totalPhotos.textContent = photoCount;
        } else {
            elements.currentIndex.textContent = '0';
            elements.totalPhotos.textContent = '0';
        }

        // Update status and submit button
        if (photoCount === 0) {
            elements.footerStatus.textContent = sdppEditor.i18n.readyToUpload;
            elements.emptyState.style.display = 'flex';
            elements.photoEditor.style.display = 'none';
            elements.gridPreview.style.display = 'none';
            elements.submitBtn.disabled = true;
        } else if (photoCount < state.photoQuantity) {
            const missing = state.photoQuantity - photoCount;
            elements.footerStatus.textContent = sdppEditor.i18n.morePhotosNeeded.replace('%d', missing);
            elements.submitBtn.disabled = true;
        } else {
            elements.footerStatus.textContent = sdppEditor.i18n.allPhotosUploaded;
            elements.submitBtn.disabled = false;
        }

        // Update navigation buttons enabled state immediately
        if (state.photos.length > 1) {
            elements.prevPhotoBtn.disabled = state.currentPhotoIndex === 0;
            elements.nextPhotoBtn.disabled = state.currentPhotoIndex === state.photos.length - 1;
        } else {
            elements.prevPhotoBtn.disabled = true;
            elements.nextPhotoBtn.disabled = true;
        }

        if (elements.previewBtn) {
            elements.previewBtn.disabled = photoCount === 0;
        }

        // Show/hide select all button
        elements.selectAllBtn.style.display = photoCount > 0 ? 'block' : 'none';

        // Update Select All Text
        const allSelected = state.selectedPhotos.size === state.photos.length && state.photos.length > 0;
        elements.selectAllBtn.textContent = allSelected ? sdppEditor.i18n.deselectAll : sdppEditor.i18n.selectAll;

        // Auto-select first photo if none is selected but photos exist
        if (state.currentPhotoIndex === -1 && state.photos.length > 0) {
            console.log('Auto-selecting first photo (via updateUI)');
            selectPhoto(0);
        }
    }

    /**
     * Get the current editor footer (text/emoji overlay) dimensions.
     * These dimensions are viewport-dependent (mobile vs desktop).
     */
    function getEditorFooterDimensions() {
        // Prefer the actual overlay, since it's the coordinate space used for dragging.
        const overlay = document.getElementById('text-overlay-layer') || document.getElementById('emoji-overlay-layer');
        const base = overlay || document.querySelector('.sdpp-polaroid-footer');
        if (!base) return null;

        const rect = base.getBoundingClientRect();
        const w = Math.max(1, rect.width);
        const h = Math.max(1, rect.height);
        return { w, h };
    }

    /**
     * Normalize layer coordinates from the current footer dimensions to the
     * fixed reference used by the server-side PNG generator.
     *
     * Desktop already matches the reference closely (factor ~= 1), so this
     * will not change desktop output.
     */
    function normalizeLayersForExport(photo) {
        const dims = photo?.editorFooterDims || getEditorFooterDimensions();

        // Fallback: if we can't measure, don't touch anything.
        if (!dims || !dims.w || !dims.h) {
            return {
                textLayers: photo?.textLayers || [],
                emojiLayers: photo?.emojiLayers || []
            };
        }

        const scaleX = EXPORT_TEXT_AREA_WIDTH / dims.w;
        const scaleY = EXPORT_TEXT_AREA_HEIGHT / dims.h;

        const normalizeTextLayers = (photo?.textLayers || []).map(layer => {
            if (!layer || typeof layer !== 'object') return layer;
            return {
                ...layer,
                x: (layer.x || 0) * scaleX,
                y: (layer.y || 0) * scaleY
            };
        });

        const normalizeEmojiLayers = (photo?.emojiLayers || []).map(layer => {
            if (!layer || typeof layer !== 'object') return layer;
            return {
                ...layer,
                x: (layer.x || 0) * scaleX,
                y: (layer.y || 0) * scaleY,
                size: (layer.size || 0) * scaleX
            };
        });

        return {
            textLayers: normalizeTextLayers,
            emojiLayers: normalizeEmojiLayers
        };
    }

    function attachEditorDimsToLayers(photo) {
        const dims = photo?.editorFooterDims || getEditorFooterDimensions();
        const w = dims?.w || EXPORT_TEXT_AREA_WIDTH;
        const h = dims?.h || EXPORT_TEXT_AREA_HEIGHT;

        const attachToText = (photo?.textLayers || []).map(layer => {
            if (!layer || typeof layer !== 'object') return layer;
            return { ...layer, editorW: w, editorH: h };
        });

        const attachToEmoji = (photo?.emojiLayers || []).map(layer => {
            if (!layer || typeof layer !== 'object') return layer;
            return { ...layer, editorW: w, editorH: h };
        });

        return {
            textLayers: attachToText,
            emojiLayers: attachToEmoji
        };
    }

    /**
     * Submit order
     */
    async function submitOrder() {
        if (state.isSubmitting) return;
        state.isSubmitting = true;
        if (elements.submitBtn) {
            elements.submitBtn.disabled = true;
        }
        if (state.photos.length === 0) {
            alert('Please upload at least one photo.');
            state.isSubmitting = false;
            if (elements.submitBtn) {
                elements.submitBtn.disabled = false;
            }
            return;
        }

        // Save current crop data
        saveCropData();

        // Show loading
        elements.loadingOverlay.style.display = 'flex';
        elements.loadingText.textContent = sdppEditor.i18n.uploadingPhoto.replace('%1$d', 1).replace('%2$d', state.photos.length);

        try {
            // Upload each photo
            const uploadedPhotos = [];

            for (let i = 0; i < state.photos.length; i++) {
                const photo = state.photos[i];
                elements.loadingText.textContent = sdppEditor.i18n.uploadingPhoto.replace('%1$d', i + 1).replace('%2$d', state.photos.length);

                let editedBlob;
                // For the currently open photo, export directly from the live cropper to be 1:1 with what user sees
                // (pinch zoom can have tiny state drift if we reconstruct via saved data).
                if (i === state.currentPhotoIndex && state.cropper) {
                    saveCropData();
                    // Export at a fixed, higher resolution to minimize rounding drift (mobile pinch can end on fractional pixels).
                    let aspectRatio = 25 / 32;
                    if (state.hasBorder) {
                        if (state.gridType === '2x3') {
                            aspectRatio = 72 / 67;
                        } else {
                            aspectRatio = 634 / 710;
                        }
                    } else {
                        if (state.gridType === '2x3') {
                            aspectRatio = 921 / 1119;
                        }
                    }

                    const exportW = 1500;
                    const exportH = Math.round(exportW / aspectRatio);

                    const liveCanvas = state.cropper.getCroppedCanvas({
                        width: exportW,
                        height: exportH,
                        imageSmoothingEnabled: true,
                        imageSmoothingQuality: 'high'
                    });
                    if (!liveCanvas) {
                        throw new Error('Falha ao gerar canvas da imagem editada.');
                    }
                    editedBlob = await canvasToBlob(liveCanvas, 'image/png', undefined, 15000);
                } else {
                    editedBlob = await getEditedBlobFromPhoto(photo);
                }
                const uploadFile = new File([editedBlob], `photo-${i + 1}.png`, { type: 'image/png' });

                // Attach editor overlay dimensions to layers so server can normalize reliably (mobile)
                const exportLayers = attachEditorDimsToLayers(photo);

                // Prepare Text JSON
                const textData = JSON.stringify(exportLayers.textLayers || []);

                // Upload via AJAX
                const formData = new FormData();
                formData.append('action', 'sdpp_upload_photo');
                formData.append('nonce', sdppEditor.nonce);
                formData.append('order_id', state.orderId);
                formData.append('photo_index', i);
                formData.append('image_file', uploadFile);
                formData.append('text', textData); // Send JSON string
                formData.append('font_family', (exportLayers.textLayers && exportLayers.textLayers[0]) ? exportLayers.textLayers[0].fontFamily : 'Pacifico'); // Fallback/Primary Font
                // Image is already pre-cropped by the editor export; sending crop_data can cause unintended recropping.
                formData.append('crop_data', '{}');

                if (photo.originalFile && (photo.originalFile.name || '').toLowerCase().endsWith('.heic')) {
                    formData.append('original_file', photo.originalFile);
                }

                const response = await fetch(sdppEditor.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });

                let result;
                try {
                    result = await response.json();
                } catch (e) {
                    const raw = await response.text();
                    throw new Error(`Upload failed (invalid JSON). HTTP ${response.status}. Response: ${raw.slice(0, 400)}`);
                }

                if (!response.ok) {
                    throw new Error(result?.data?.message || `Upload failed. HTTP ${response.status}`);
                }

                if (result.success) {
                    // Merge local state (emojis) with server response
                    const photoData = result.data;

                    photoData.textLayers = exportLayers.textLayers || [];
                    photoData.emojiLayers = exportLayers.emojiLayers || [];

                    // Serialized versions for backend persistence
                    if (!photoData.text && exportLayers.textLayers && exportLayers.textLayers.length > 0) {
                        photoData.text = JSON.stringify(exportLayers.textLayers);
                    }
                    // Always serialize emoji as JSON string for backend
                    if (exportLayers.emojiLayers && exportLayers.emojiLayers.length > 0) {
                        photoData.emoji = JSON.stringify(exportLayers.emojiLayers);
                    }

                    photoData.crop_x = 0;
                    photoData.crop_y = 0;
                    photoData.crop_width = 0;
                    photoData.crop_height = 0;
                    photoData.zoom = photo.zoom || 1;
                    photoData.rotation = photo.rotation || 0;

                    uploadedPhotos.push(photoData);
                } else {
                    throw new Error(result.data?.message || 'Upload failed');
                }
            }

            // Save all photos
            elements.loadingText.textContent = sdppEditor.i18n.savingOrder;

            const saveFormData = new FormData();
            saveFormData.append('action', 'sdpp_save_photos');
            saveFormData.append('nonce', sdppEditor.nonce);
            saveFormData.append('order_id', state.orderId);
            saveFormData.append('photos', JSON.stringify(uploadedPhotos));

            const saveResponse = await fetch(sdppEditor.ajaxUrl, {
                method: 'POST',
                body: saveFormData
            });

            let saveResult;
            try {
                saveResult = await saveResponse.json();
            } catch (e) {
                const raw = await saveResponse.text();
                throw new Error(`Save failed (invalid JSON). HTTP ${saveResponse.status}. Response: ${raw.slice(0, 400)}`);
            }

            if (!saveResponse.ok) {
                throw new Error(saveResult?.data?.message || `Save failed. HTTP ${saveResponse.status}`);
            }

            if (saveResult.success) {
                // Show success modal
                elements.loadingOverlay.style.display = 'none';
                elements.successModal.style.display = 'flex';
            } else {
                throw new Error(saveResult.data?.message || 'Save failed');
            }

        } catch (error) {
            console.error('Submit error:', error);
            elements.loadingOverlay.style.display = 'none';
            alert(error?.message || sdppEditor.i18n.submitError);
        } finally {
            state.isSubmitting = false;
            if (elements.submitBtn) {
                elements.submitBtn.disabled = false;
            }
        }
    }

    /**
     * Get cropped image for non-current photos
     * Uses original crop dimensions to preserve image quality
     * NOTE: Currently unused in the submit flow — kept for potential future use.
     */
    function getCroppedBlob(photo) {
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');

                // Apply crop if data exists - use ORIGINAL crop dimensions for max quality
                if (photo.cropData && photo.cropData.width && photo.cropData.height) {
                    // Use original crop pixels for maximum sharpness
                    const cropW = Math.round(photo.cropData.width);
                    const cropH = Math.round(photo.cropData.height);

                    canvas.width = cropW;
                    canvas.height = cropH;

                    ctx.drawImage(
                        img,
                        photo.cropData.x,
                        photo.cropData.y,
                        photo.cropData.width,
                        photo.cropData.height,
                        0,
                        0,
                        cropW,
                        cropH
                    );
                } else {
                    // Bug 13 fix: use proper aspect ratio instead of always square
                    const ar = getCurrentAspectRatio();
                    let cropW, cropH, x, y;
                    if (img.width / img.height > ar) {
                        // Image is wider than target: constrain by height
                        cropH = img.height;
                        cropW = Math.round(cropH * ar);
                        x = Math.round((img.width - cropW) / 2);
                        y = 0;
                    } else {
                        // Image is taller than target: constrain by width
                        cropW = img.width;
                        cropH = Math.round(cropW / ar);
                        x = 0;
                        y = Math.round((img.height - cropH) / 2);
                    }

                    canvas.width = cropW;
                    canvas.height = cropH;

                    ctx.drawImage(img, x, y, cropW, cropH, 0, 0, cropW, cropH);
                }

                canvasToBlob(canvas, 'image/png').then(resolve);
            };
            img.src = photo.dataUrl;
        });
    }

    function getEditedBlobFromPhoto(photo) {
        return ensurePhotoCropData(photo).then((crop) => {
            photo.cropData = crop;

            return new Promise((resolve, reject) => {
                const timeoutMs = 15000;
                let done = false;

                const t = setTimeout(() => {
                    if (done) return;
                    done = true;
                    reject(new Error('Timeout ao exportar imagem. Tente novamente com fotos menores ou em uma rede melhor.'));
                }, timeoutMs);

                const img = new Image();
                img.onload = () => {
                    const container = document.createElement('div');
                    container.style.position = 'fixed';
                    container.style.left = '-10000px';
                    container.style.top = '-10000px';
                    const cw = Math.max(1, Math.round(photo.containerData?.width || 1200));
                    const ch = Math.max(1, Math.round(photo.containerData?.height || 1200));
                    container.style.width = `${cw}px`;
                    container.style.height = `${ch}px`;
                    container.style.overflow = 'hidden';
                    container.style.opacity = '0';
                    container.style.pointerEvents = 'none';
                    img.style.display = 'block';
                    img.style.maxWidth = 'none';
                    img.style.maxHeight = 'none';
                    container.appendChild(img);
                    document.body.appendChild(container);

                    let tmpCropper;
                    const cleanup = () => {
                        try {
                            if (tmpCropper) tmpCropper.destroy();
                        } catch (e) {
                        }
                        try {
                            container.remove();
                        } catch (e) {
                        }
                    };

                    const finalizeResolve = (blob) => {
                        if (done) return;
                        done = true;
                        clearTimeout(t);
                        cleanup();
                        resolve(blob);
                    };

                    const finalizeReject = (err) => {
                        if (done) return;
                        done = true;
                        clearTimeout(t);
                        cleanup();
                        reject(err);
                    };

                    let aspectRatio = 25 / 32; // Default borderless (3x3)
                    if (state.hasBorder) {
                        if (state.gridType === '2x3') {
                            aspectRatio = 72 / 67;
                        } else {
                            aspectRatio = 634 / 710;
                        }
                    } else {
                        if (state.gridType === '2x3') {
                            aspectRatio = 921 / 1119;
                        }
                    }

                    setTimeout(() => {
                        try {
                            tmpCropper = new Cropper(img, {
                                aspectRatio: aspectRatio,
                                viewMode: 1,
                                dragMode: 'move',
                                autoCropArea: 1,
                                cropBoxMovable: false,
                                cropBoxResizable: false,
                                toggleDragModeOnDblclick: false,
                                guides: false,
                                center: false,
                                highlight: false,
                                background: false,
                                modal: false,
                                ready: () => {
                                    try {
                                        // Match editor preview exactly:
                                        // The visible framing is defined by canvasData (zoom/position) + cropData + rotation.
                                        // Use the same order as loadImageInCropper().
                                        // IMPORTANT: canvasData depends on container size.
                                        // We recreate the container with the original containerData dims to avoid drift.
                                        if (photo.canvasData) {
                                            tmpCropper.setCanvasData(photo.canvasData);
                                        }
                                        if (photo.cropData) {
                                            tmpCropper.setData(photo.cropData);
                                        }
                                        if (photo.rotation) {
                                            tmpCropper.rotateTo(photo.rotation);
                                        }

                                        const canvas = tmpCropper.getCroppedCanvas({
                                            imageSmoothingEnabled: true,
                                            imageSmoothingQuality: 'high'
                                        });
                                        if (!canvas) {
                                            finalizeReject(new Error('Falha ao gerar canvas da imagem editada.'));
                                            return;
                                        }

                                        canvasToBlob(canvas, 'image/png', undefined, 15000)
                                            .then(finalizeResolve)
                                            .catch(finalizeReject);
                                    } catch (e) {
                                        finalizeReject(e);
                                    }
                                }
                            });
                        } catch (e) {
                            finalizeReject(e);
                        }
                    }, 0);
                };

                img.onerror = () => {
                    if (done) return;
                    done = true;
                    clearTimeout(t);
                    reject(new Error('Falha ao carregar imagem para exportação.'));
                };

                img.src = photo.dataUrl;
            });
        });
    }

    function ensurePhotoCropData(photo) {
        if (photo && photo.cropData && photo.cropData.width && photo.cropData.height) {
            return Promise.resolve(photo.cropData);
        }

        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                const imgW = img.naturalWidth || img.width;
                const imgH = img.naturalHeight || img.height;

                let aspectRatio = 25 / 32; // Default borderless (3x3)
                if (state.hasBorder) {
                    if (state.gridType === '2x3') {
                        aspectRatio = 72 / 67;
                    } else {
                        aspectRatio = 634 / 710;
                    }
                } else {
                    if (state.gridType === '2x3') {
                        aspectRatio = 921 / 1119;
                    }
                }

                const targetRatio = aspectRatio;
                const imgRatio = imgW / imgH;

                let cropW;
                let cropH;
                let cropX;
                let cropY;

                if (imgRatio > targetRatio) {
                    cropH = imgH;
                    cropW = imgH * targetRatio;
                    cropX = (imgW - cropW) / 2;
                    cropY = 0;
                } else {
                    cropW = imgW;
                    cropH = imgW / targetRatio;
                    cropX = 0;
                    cropY = (imgH - cropH) / 2;
                }

                resolve({
                    x: cropX,
                    y: cropY,
                    width: cropW,
                    height: cropH,
                    rotate: 0,
                    scaleX: 1,
                    scaleY: 1
                });
            };
            img.src = photo.dataUrl;
        });
    }

    function canvasToBlob(canvas, type, quality, timeoutMs) {
        return new Promise((resolve, reject) => {
            let done = false;

            const t = setTimeout(() => {
                if (done) return;
                done = true;
                reject(new Error('Timeout ao gerar imagem (canvas.toBlob).'));
            }, Math.max(1000, timeoutMs || 0) || 0);

            canvas.toBlob((blob) => {
                if (done) return;
                done = true;
                if (t) clearTimeout(t);

                if (!blob) {
                    reject(new Error('Falha ao gerar blob da imagem.'));
                    return;
                }
                resolve(blob);
            }, type, quality);
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
