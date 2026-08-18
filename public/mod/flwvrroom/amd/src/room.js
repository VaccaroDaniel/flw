define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    var started = Date.now();

    var clamp = function(value, min, max) {
        return Math.max(min, Math.min(max, value));
    };

    var wrap = function(value) {
        return ((value % 100) + 100) % 100;
    };

    var signedDelta = function(value) {
        var delta = wrap(value);
        return delta > 50 ? delta - 100 : delta;
    };

    var updateScore = function(root, passinggrade, maxgrade) {
        var score = 0;
        var completed = [];

        root.querySelectorAll('[data-hotspot].is-complete').forEach(function(button) {
            score += parseInt(button.getAttribute('data-score'), 10) || 0;
            completed.push(button.getAttribute('data-hotspot'));
        });

        var answer = root.querySelector('input[type=radio]:checked');
        if (answer) {
            score += parseInt(answer.value, 10) || 0;
        }

        if (root.getAttribute('data-role-complete') === '1') {
            score += parseInt(root.getAttribute('data-role-score'), 10) || 0;
            completed.push('rolecharacter');
        }

        score = clamp(score, 0, maxgrade);
        root.querySelector('[data-region="score"]').textContent = score;
        root.classList.toggle('is-passed', score >= passinggrade);

        return {
            score: score,
            completed: completed
        };
    };

    var init = function(config) {
        if (typeof config === 'string') {
            var configRoot = document.getElementById(config);
            var configNode = configRoot ? configRoot.querySelector('[data-region="flwvrroom-config"]') : null;
            try {
                config = configNode ? JSON.parse(configNode.textContent || '{}') : {rootid: config};
            } catch (error) {
                config = {rootid: config};
            }
        }

        var root = document.getElementById(config.rootid);
        if (!root) {
            return;
        }
        if (config.rolecharacter && config.rolecharacter.enabled) {
            root.setAttribute('data-role-score', config.rolecharacter.score || 0);
        }
        var roleButton = root.querySelector('[data-action="open-role-character"]');
        var getEditorField = function(name) {
            return root.querySelector('[data-editor-field="' + name + '"]');
        };
        var appendEditorLine = function(name, line) {
            var field = getEditorField(name);
            if (!field) {
                return;
            }
            field.value = field.value.trim() ? field.value.trim() + "\n" + line : line;
            field.dispatchEvent(new Event('input', {bubbles: true}));
        };
        var replaceEditorLine = function(name, index, line) {
            var field = getEditorField(name);
            if (!field) {
                return false;
            }
            var lines = String(field.value || '').split(/\r?\n/);
            if (index < 0 || index >= lines.length) {
                return false;
            }
            lines[index] = line;
            field.value = lines.join("\n");
            field.dispatchEvent(new Event('input', {bubbles: true}));
            return true;
        };
        var openRoleCharacter = function() {
            if (roleButton) {
                roleButton.click();
            }
        };
        var setModelStatus = function(message, warning) {
            var node = root.querySelector('[data-region="model-status"]');
            if (!node) {
                return;
            }
            node.textContent = message || '';
            node.hidden = !message;
            node.classList.toggle('is-warning', !!warning);
        };
        var publishObjectRefs = function(refs) {
            var select = root.querySelector('[data-region="object-browser-select"]');
            var status = root.querySelector('[data-region="object-browser-status"]');
            if (!select) {
                return;
            }
            select.innerHTML = '';
            refs = (refs || []).filter(function(ref, index, list) {
                return ref && list.indexOf(ref) === index;
            }).sort();
            refs.forEach(function(ref) {
                var option = document.createElement('option');
                option.value = ref;
                option.textContent = ref;
                select.appendChild(option);
            });
            if (status) {
                status.textContent = refs.length ?
                    (config.strings.objectbrowserready || '{$a} objects').replace('{$a}', refs.length) :
                    (config.strings.objectbrowserempty || 'No named 3D objects found yet.');
            }
        };
        var hotspotPartsFromLine = function(line) {
            var parts = String(line || '').split('|');
            while (parts.length < 12) {
                parts.push('');
            }
            return parts;
        };
        var findLiveHotspotButton = function(key) {
            var found = null;
            root.querySelectorAll('[data-hotspot]').forEach(function(button) {
                if (button.getAttribute('data-hotspot') === key) {
                    found = button;
                }
            });
            return found;
        };
        var updateProgressHotspot = function(oldKey, parts) {
            var item = null;
            root.querySelectorAll('[data-progress-hotspot]').forEach(function(candidate) {
                if (candidate.getAttribute('data-progress-hotspot') === oldKey) {
                    item = candidate;
                }
            });
            if (item) {
                item.setAttribute('data-progress-hotspot', parts[0] || oldKey);
                item.textContent = parts[1] || parts[0] || oldKey;
            }
        };
        var applyHotspotPartsToButton = function(button, parts, oldKey) {
            if (!button) {
                return;
            }
            var key = parts[0] || oldKey || 'hotspot';
            var label = parts[1] || key;
            button.setAttribute('data-hotspot', key);
            button.setAttribute('data-score', parseInt(parts[2], 10) || 0);
            button.setAttribute('data-world-x', parseFloat(parts[3]) || 50);
            button.setAttribute('data-world-y', parseFloat(parts[4]) || 50);
            button.setAttribute('data-hotspot-label', label);
            button.setAttribute('data-hotspot-description', parts[5] || '');
            button.setAttribute('data-hotspot-audio', parts[6] || '');
            button.setAttribute('data-object-x', parts[7] || '');
            button.setAttribute('data-object-y', parts[8] || '');
            button.setAttribute('data-object-z', parts[9] || '');
            button.setAttribute('data-hotspot-kpcodes', parts[10] || '');
            button.setAttribute('data-hotspot-objectref', parts[11] || '');
            var labelNode = button.querySelector('span');
            if (labelNode) {
                labelNode.textContent = label;
            } else {
                button.textContent = label;
            }
            updateProgressHotspot(oldKey || key, parts);
        };
        var createLiveHotspotFromLine = function(line) {
            var parts = hotspotPartsFromLine(line);
            var container = root.querySelector('[data-region="panorama"]');
            if (!container || !parts[0] || findLiveHotspotButton(parts[0])) {
                return;
            }
            var button = document.createElement('button');
            button.className = 'flwvrroom-hotspot';
            button.type = 'button';
            button.setAttribute('aria-pressed', 'false');
            button.appendChild(document.createElement('span'));
            applyHotspotPartsToButton(button, parts, parts[0]);
            button.addEventListener('click', function() {
                button.classList.add('is-complete');
                button.setAttribute('aria-pressed', 'true');
                if (typeof showHotspotCard === 'function') {
                    showHotspotCard(button);
                }
                updateScore(root, config.passinggrade, config.maxgrade);
                if (typeof updateMissionProgress === 'function') {
                    updateMissionProgress();
                }
            });
            container.appendChild(button);

            var progressList = root.querySelector('.flwvrroom-mission-checklist ul');
            if (progressList && !root.querySelector('[data-progress-hotspot="' + parts[0] + '"]')) {
                var item = document.createElement('li');
                item.setAttribute('data-progress-hotspot', parts[0]);
                item.textContent = parts[1] || parts[0];
                progressList.insertBefore(item, progressList.firstChild);
            }
            if (typeof renderRotation === 'function') {
                renderRotation();
            }
        };
        var syncLiveHotspotFromLine = function(oldLine, newLine) {
            var oldParts = hotspotPartsFromLine(oldLine);
            var parts = hotspotPartsFromLine(newLine);
            var oldKey = oldParts[0] || parts[0];
            var button = findLiveHotspotButton(oldKey) || findLiveHotspotButton(parts[0]);
            if (!button) {
                createLiveHotspotFromLine(newLine);
                return;
            }
            applyHotspotPartsToButton(button, parts, oldKey);
        };
        var removeLiveHotspotForLine = function(line) {
            var key = hotspotPartsFromLine(line)[0];
            var button = findLiveHotspotButton(key);
            if (button && button.parentNode) {
                button.parentNode.removeChild(button);
            }
            root.querySelectorAll('[data-progress-hotspot]').forEach(function(item) {
                if (item.getAttribute('data-progress-hotspot') === key && item.parentNode) {
                    item.parentNode.removeChild(item);
                }
            });
        };

        var stage = root.querySelector('[data-region="panorama-stage"]');
        var panorama = root.querySelector('[data-region="panorama"]');
        if (stage) {
            var rotation = 50;
            var offsetPx = 0;
            var visibleSpan = 50;
            var hotspots = root.querySelectorAll('[data-hotspot]');
            var threeState = null;
            var roomMode = config.roommode || stage.getAttribute('data-room-mode') || 'panorama';

            var projectRoleButton = function() {
                if (!roleButton) {
                    return;
                }

                if (threeState && threeState.projectRoleCharacter) {
                    threeState.projectRoleCharacter(roleButton);
                    return;
                }

                roleButton.style.left = '';
                roleButton.style.top = '';
                roleButton.style.bottom = '';
                roleButton.classList.remove('is-out-of-view');
                roleButton.setAttribute('aria-hidden', 'false');
                roleButton.tabIndex = 0;
            };

            var renderRotation = function() {
                if (panorama) {
                    panorama.style.backgroundPosition = offsetPx + 'px center';
                }
                if (threeState) {
                    threeState.lon = (rotation / 100) * 360;
                    threeState.render();
                    if (threeState.projectHotspots) {
                        threeState.projectHotspots(hotspots);
                    }
                    projectRoleButton();
                    return;
                }

                hotspots.forEach(function(button) {
                    var worldX = parseFloat(button.getAttribute('data-world-x'));
                    var worldY = parseFloat(button.getAttribute('data-world-y'));
                    if (isNaN(worldX) || isNaN(worldY)) {
                        return;
                    }

                    var delta = signedDelta(worldX - rotation);
                    var visible = Math.abs(delta) <= visibleSpan / 2;
                    var screenX = 50 + (delta / (visibleSpan / 2)) * 50;

                    button.style.left = screenX + '%';
                    button.style.top = worldY + '%';
                    button.classList.toggle('is-out-of-view', !visible);
                    button.setAttribute('aria-hidden', visible ? 'false' : 'true');
                    button.tabIndex = visible ? 0 : -1;
                });
                projectRoleButton();
            };

            var rotateBy = function(amount) {
                rotation = wrap(rotation + amount);
                offsetPx += (amount / 100) * stage.clientWidth * 2;
                renderRotation();
            };

            var moveBy = function(amount) {
                if (threeState && threeState.moveForward) {
                    threeState.moveForward(amount);
                }
            };

            renderRotation();

            var setupThreeViewer = function(THREE, LoaderModule) {
                var container = root.querySelector('[data-region="three-viewer"]');
                if (roomMode === 'builtin3d') {
                    setupBuiltin3dRoom(THREE, LoaderModule ? LoaderModule.GLTFLoader : null, container);
                    return;
                }

                if (roomMode === 'uploaded3d') {
                    setupUploaded3dRoom(THREE, LoaderModule ? LoaderModule.GLTFLoader : null, container);
                    return;
                }

                setupPanoramaViewer(THREE, container);
            };

            var setupPanoramaViewer = function(THREE, container) {
                var backgroundUrl = panorama ? panorama.getAttribute('data-background-url') : null;
                if (!container || !backgroundUrl) {
                    return;
                }

                var width = Math.max(1, container.clientWidth);
                var height = Math.max(1, container.clientHeight);
                var scene = new THREE.Scene();
                var camera = new THREE.PerspectiveCamera(75, width / height, 1, 1100);
                var renderer = new THREE.WebGLRenderer({antialias: true});
                renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
                renderer.setSize(width, height);
                if (renderer.xr) {
                    renderer.xr.enabled = true;
                }
                container.appendChild(renderer.domElement);

                var geometry = new THREE.SphereGeometry(500, 60, 40);
                geometry.scale(-1, 1, 1);

                new THREE.TextureLoader().load(backgroundUrl, function(texture) {
                    texture.colorSpace = THREE.SRGBColorSpace;
                    scene.add(new THREE.Mesh(geometry, new THREE.MeshBasicMaterial({map: texture})));
                    renderRotation();
                });

                var state = {
                    lon: 180,
                    lat: 0,
                    render: function() {
                        if (renderer.xr && renderer.xr.isPresenting) {
                            renderer.render(scene, camera);
                            return;
                        }
                        var phi = THREE.MathUtils.degToRad(90 - state.lat);
                        var theta = THREE.MathUtils.degToRad(state.lon);
                        camera.lookAt(
                            500 * Math.sin(phi) * Math.cos(theta),
                            500 * Math.cos(phi),
                            500 * Math.sin(phi) * Math.sin(theta)
                        );
                        renderer.render(scene, camera);
                    },
                    enterWebXR: function() {
                        if (!navigator.xr || !renderer.xr) {
                            return Promise.reject(new Error('WebXR unavailable'));
                        }
                        return navigator.xr.requestSession('immersive-vr', {
                            optionalFeatures: ['local-floor', 'bounded-floor']
                        }).then(function(session) {
                            return Promise.resolve(renderer.xr.setSession(session)).then(function() {
                                renderer.setAnimationLoop(function() {
                                    state.render();
                                });
                                session.addEventListener('end', function() {
                                    renderer.setAnimationLoop(null);
                                    state.render();
                                });
                                return session;
                            });
                        });
                    }
                };
                threeState = state;
                state.render();

                window.addEventListener('resize', function() {
                    var newWidth = Math.max(1, container.clientWidth);
                    var newHeight = Math.max(1, container.clientHeight);
                    camera.aspect = newWidth / newHeight;
                    camera.updateProjectionMatrix();
                    renderer.setSize(newWidth, newHeight);
                    state.render();
                });
            };

            var getRolePosition = function(defaultPosition) {
                var position = config.rolecharacter && config.rolecharacter.position ? config.rolecharacter.position : {};
                return {
                    x: Number.isFinite(parseFloat(position.x)) ? parseFloat(position.x) : defaultPosition.x,
                    y: Number.isFinite(parseFloat(position.y)) ? parseFloat(position.y) : defaultPosition.y,
                    z: Number.isFinite(parseFloat(position.z)) ? parseFloat(position.z) : defaultPosition.z
                };
            };

            var markRoleObject = function(object) {
                object.userData.roleCharacter = true;
                if (object.children) {
                    object.children.forEach(markRoleObject);
                }
            };

            var createRoleCharacter = function(THREE, GLTFLoader, position, scale) {
                var roleScale = scale || 1;
                var group = new THREE.Group();
                group.position.set(position.x, position.y, position.z);
                group.userData.roleBaseY = position.y;
                group.userData.roleAnchorYOffset = 1.78 * roleScale;

                var body = new THREE.Mesh(
                    new THREE.CylinderGeometry(0.25 * roleScale, 0.34 * roleScale, 1.08 * roleScale, 28),
                    new THREE.MeshStandardMaterial({color: 0x2563eb, roughness: 0.62})
                );
                body.position.y = 0.72 * roleScale;

                var head = new THREE.Mesh(
                    new THREE.SphereGeometry(0.25 * roleScale, 28, 18),
                    new THREE.MeshStandardMaterial({color: 0xf1c9a5, roughness: 0.58})
                );
                head.position.y = 1.42 * roleScale;

                var cap = new THREE.Mesh(
                    new THREE.CylinderGeometry(0.22 * roleScale, 0.25 * roleScale, 0.12 * roleScale, 24),
                    new THREE.MeshStandardMaterial({color: 0x1e293b, roughness: 0.7})
                );
                cap.position.y = 1.65 * roleScale;

                var leftArm = new THREE.Mesh(
                    new THREE.BoxGeometry(0.12 * roleScale, 0.72 * roleScale, 0.12 * roleScale),
                    new THREE.MeshStandardMaterial({color: 0x1d4ed8, roughness: 0.65})
                );
                leftArm.position.set(-0.36 * roleScale, 0.78 * roleScale, 0);
                leftArm.rotation.z = 0.28;

                var rightArm = leftArm.clone();
                rightArm.position.x = 0.38 * roleScale;
                rightArm.rotation.z = -0.75;

                var base = new THREE.Mesh(
                    new THREE.CylinderGeometry(0.48 * roleScale, 0.54 * roleScale, 0.06 * roleScale, 32),
                    new THREE.MeshStandardMaterial({color: 0x7c3aed, roughness: 0.48})
                );
                base.position.y = 0.03 * roleScale;

                group.add(base);
                group.add(body);
                group.add(head);
                group.add(cap);
                group.add(leftArm);
                group.add(rightArm);
                group.userData.animateRole = function(now) {
                    group.position.y = group.userData.roleBaseY + Math.sin(now / 520) * 0.025 * roleScale;
                    rightArm.rotation.z = -0.78 + Math.sin(now / 260) * 0.18;
                };

                markRoleObject(group);
                if (GLTFLoader && config.rolecharacter && config.rolecharacter.modelurl) {
                    var loader = new GLTFLoader();
                    loader.load(config.rolecharacter.modelurl, function(gltf) {
                        var model = gltf.scene || gltf.scenes[0];
                        if (!model) {
                            return;
                        }

                        group.clear();
                        model.updateMatrixWorld(true);
                        var box = new THREE.Box3().setFromObject(model);
                        var size = box.getSize(new THREE.Vector3());
                        var center = box.getCenter(new THREE.Vector3());
                        var targetHeight = 1.72 * roleScale;
                        var modelScale = targetHeight / Math.max(size.y, size.x, size.z, 0.001);
                        model.scale.setScalar(modelScale);
                        model.position.set(-center.x * modelScale, -box.min.y * modelScale, -center.z * modelScale);
                        group.userData.roleAnchorYOffset = Math.max(targetHeight, 1.2 * roleScale);
                        group.add(model);
                        markRoleObject(group);
                    }, null, function() {
                        // Keep the built-in character visible if the uploaded character model cannot load.
                    });
                }

                return group;
            };

            var setupBuiltin3dRoom = function(THREE, GLTFLoader, container) {
                if (!container) {
                    return;
                }

                var width = Math.max(1, container.clientWidth);
                var height = Math.max(1, container.clientHeight);
                var scene = new THREE.Scene();
                scene.background = new THREE.Color(0xdfe7ed);

                var camera = new THREE.PerspectiveCamera(62, width / height, 0.1, 100);
                camera.position.set(0, 1.65, 5.2);

                var renderer = new THREE.WebGLRenderer({antialias: true});
                renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
                renderer.setSize(width, height);
                if (renderer.xr) {
                    renderer.xr.enabled = true;
                }
                container.appendChild(renderer.domElement);

                scene.add(new THREE.HemisphereLight(0xffffff, 0x7c5c45, 1.8));
                var keyLight = new THREE.DirectionalLight(0xffffff, 1.2);
                keyLight.position.set(-3, 5, 4);
                scene.add(keyLight);

                var clickableObjects = [];
                var addBox = function(key, size, position, color) {
                    var mesh = new THREE.Mesh(
                        new THREE.BoxGeometry(size.x, size.y, size.z),
                        new THREE.MeshStandardMaterial({color: color, roughness: 0.72})
                    );
                    mesh.name = key;
                    mesh.position.set(position.x, position.y, position.z);
                    mesh.userData.hotspotKey = key;
                    scene.add(mesh);
                    clickableObjects.push(mesh);
                    return mesh;
                };

                var floor = new THREE.Mesh(
                    new THREE.PlaneGeometry(11, 11),
                    new THREE.MeshStandardMaterial({color: 0x7b8a78, roughness: 0.9})
                );
                floor.rotation.x = -Math.PI / 2;
                scene.add(floor);

                var backWall = new THREE.Mesh(
                    new THREE.PlaneGeometry(11, 4),
                    new THREE.MeshStandardMaterial({color: 0xf3eadc, roughness: 0.85})
                );
                backWall.position.set(0, 2, -4.2);
                scene.add(backWall);

                var leftWall = new THREE.Mesh(
                    new THREE.PlaneGeometry(9, 4),
                    new THREE.MeshStandardMaterial({color: 0xe5d5c3, roughness: 0.85})
                );
                leftWall.position.set(-5.5, 2, 0.1);
                leftWall.rotation.y = Math.PI / 2;
                scene.add(leftWall);

                var rightWall = leftWall.clone();
                rightWall.material = new THREE.MeshStandardMaterial({color: 0xe8dccf, roughness: 0.85});
                rightWall.position.x = 5.5;
                rightWall.rotation.y = -Math.PI / 2;
                scene.add(rightWall);

                addBox('cashier', {x: 2.2, y: 1.1, z: 0.75}, {x: 2.6, y: 0.55, z: -2.8}, 0x8b5e3c);
                addBox('table', {x: 2.0, y: 0.18, z: 1.2}, {x: 0, y: 0.82, z: -1.05}, 0x9a6a3d);
                addBox('menu', {x: 0.55, y: 0.04, z: 0.38}, {x: 0.75, y: 1.08, z: -1.15}, 0x1d4ed8);

                var cup = new THREE.Mesh(
                    new THREE.CylinderGeometry(0.16, 0.12, 0.32, 24),
                    new THREE.MeshStandardMaterial({color: 0xffffff, roughness: 0.58})
                );
                cup.position.set(-0.45, 1.12, -1.05);
                cup.name = 'cup';
                cup.userData.hotspotKey = 'cup';
                scene.add(cup);
                clickableObjects.push(cup);

                var roleCharacter = null;
                if (config.rolecharacter && config.rolecharacter.enabled) {
                    roleCharacter = createRoleCharacter(THREE, GLTFLoader, getRolePosition({x: -2.2, y: 0, z: -2.6}), 1);
                    scene.add(roleCharacter);
                    clickableObjects.push(roleCharacter);
                } else {
                    var waiter = new THREE.Group();
                    var body = new THREE.Mesh(
                        new THREE.CylinderGeometry(0.25, 0.32, 1.1, 24),
                        new THREE.MeshStandardMaterial({color: 0x2563eb, roughness: 0.65})
                    );
                    body.position.y = 0.7;
                    var head = new THREE.Mesh(
                        new THREE.SphereGeometry(0.24, 24, 16),
                        new THREE.MeshStandardMaterial({color: 0xf1c9a5, roughness: 0.62})
                    );
                    head.position.y = 1.38;
                    waiter.add(body);
                    waiter.add(head);
                    waiter.position.set(-2.2, 0, -2.6);
                    waiter.name = 'waiter';
                    waiter.userData.hotspotKey = 'waiter';
                    scene.add(waiter);
                    clickableObjects.push(body, head);
                    body.name = 'waiter-body';
                    head.name = 'waiter-head';
                    body.userData.hotspotKey = 'waiter';
                    head.userData.hotspotKey = 'waiter';
                }

                var sign = addBox('room-sign', {x: 1.8, y: 0.42, z: 0.08}, {x: -0.7, y: 2.8, z: -4.1}, 0x22543d);
                clickableObjects.pop();
                sign.userData.hotspotKey = '';

                var raycaster = new THREE.Raycaster();
                var pointer = new THREE.Vector2();
                var setPointerFromEvent = function(event) {
                    var rect = renderer.domElement.getBoundingClientRect();
                    pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
                    pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
                    raycaster.setFromCamera(pointer, camera);
                };
                var findHotspotButton = function(key) {
                    var found = null;
                    hotspots.forEach(function(button) {
                        if (button.getAttribute('data-hotspot') === key) {
                            found = button;
                        }
                    });
                    return found;
                };
                var findHotspotButtonForObject = function(object) {
                    var current = object;
                    while (current) {
                        var reference = current.name || current.userData.hotspotKey || current.uuid || '';
                        if (reference) {
                            var found = null;
                            hotspots.forEach(function(button) {
                                if ((button.getAttribute('data-hotspot-objectref') || '') === reference) {
                                    found = button;
                                }
                            });
                            if (found) {
                                return found;
                            }
                        }
                        current = current.parent;
                    }
                    return null;
                };
                var activateHit = function(hit) {
                    if (!hit) {
                        return;
                    }
                    if (hit.object.userData.roleCharacter) {
                        openRoleCharacter();
                        return;
                    }
                    var referencedButton = findHotspotButtonForObject(hit.object);
                    if (referencedButton) {
                        referencedButton.click();
                        return;
                    }
                    if (!hit.object.userData.hotspotKey) {
                        return;
                    }
                    var button = findHotspotButton(hit.object.userData.hotspotKey);
                    if (button) {
                        button.click();
                    }
                };

                renderer.domElement.addEventListener('click', function(event) {
                    setPointerFromEvent(event);
                    activateHit(raycaster.intersectObjects(clickableObjects, true)[0]);
                });

                var controller = null;
                var controllerMatrix = new THREE.Matrix4();
                var pickFromController = function(source) {
                    controllerMatrix.identity().extractRotation(source.matrixWorld);
                    raycaster.ray.origin.setFromMatrixPosition(source.matrixWorld);
                    raycaster.ray.direction.set(0, 0, -1).applyMatrix4(controllerMatrix);
                    return raycaster.intersectObjects(clickableObjects, true)[0];
                };

                var state = {
                    lon: 180,
                    bounds: {
                        minX: -4.8,
                        maxX: 4.8,
                        minZ: -3.8,
                        maxZ: 5.2
                    },
                    render: function() {
                        if (roleCharacter && roleCharacter.userData.animateRole) {
                            roleCharacter.userData.animateRole(Date.now());
                        }
                        var yaw = THREE.MathUtils.degToRad(state.lon - 180);
                        if (!(renderer.xr && renderer.xr.isPresenting)) {
                            camera.lookAt(
                                camera.position.x + Math.sin(yaw) * 10,
                                1.45,
                                camera.position.z - Math.cos(yaw) * 10
                            );
                        }
                        renderer.render(scene, camera);
                    },
                    enterWebXR: function() {
                        if (!navigator.xr || !renderer.xr) {
                            return Promise.reject(new Error('WebXR unavailable'));
                        }
                        if (!controller) {
                            controller = renderer.xr.getController(0);
                            controller.addEventListener('selectstart', function() {
                                activateHit(pickFromController(controller));
                            });
                            scene.add(controller);
                        }
                        return navigator.xr.requestSession('immersive-vr', {
                            optionalFeatures: ['local-floor', 'bounded-floor']
                        }).then(function(session) {
                            return Promise.resolve(renderer.xr.setSession(session)).then(function() {
                                renderer.setAnimationLoop(function() {
                                    state.render();
                                });
                                session.addEventListener('end', function() {
                                    renderer.setAnimationLoop(null);
                                    state.render();
                                });
                                return session;
                            });
                        });
                    },
                    moveForward: function(amount) {
                        var yaw = THREE.MathUtils.degToRad(state.lon - 180);
                        camera.position.x = clamp(camera.position.x + Math.sin(yaw) * amount, state.bounds.minX, state.bounds.maxX);
                        camera.position.z = clamp(camera.position.z - Math.cos(yaw) * amount, state.bounds.minZ, state.bounds.maxZ);
                        renderRotation();
                    },
                    capturePosition: function(event) {
                        setPointerFromEvent(event);
                        var hit = raycaster.intersectObject(floor, false)[0] ||
                            raycaster.intersectObjects(clickableObjects, true)[0];
                        if (hit && hit.object) {
                            hit.point.objectref = hit.object.name || hit.object.userData.hotspotKey || hit.object.uuid || '';
                        }
                        return hit ? hit.point : null;
                    },
                    projectHotspots: function(buttons) {
                        buttons.forEach(function(button) {
                            var x = parseFloat(button.getAttribute('data-object-x'));
                            var y = parseFloat(button.getAttribute('data-object-y'));
                            var z = parseFloat(button.getAttribute('data-object-z'));
                            if (isNaN(x) || isNaN(y) || isNaN(z)) {
                                return;
                            }

                            var point = new THREE.Vector3(x, y, z).project(camera);
                            var visible = point.z > -1 && point.z < 1 && Math.abs(point.x) <= 1.08 && Math.abs(point.y) <= 1.08;
                            button.style.left = ((point.x * 0.5 + 0.5) * 100) + '%';
                            button.style.top = ((-point.y * 0.5 + 0.5) * 100) + '%';
                            button.classList.toggle('is-out-of-view', !visible);
                            button.setAttribute('aria-hidden', visible ? 'false' : 'true');
                            button.tabIndex = visible ? 0 : -1;
                        });
                    },
                    projectRoleCharacter: function(button) {
                        if (!roleCharacter) {
                            return;
                        }

                        var point = new THREE.Vector3(
                            roleCharacter.position.x,
                            roleCharacter.position.y + roleCharacter.userData.roleAnchorYOffset,
                            roleCharacter.position.z
                        ).project(camera);
                        var visible = point.z > -1 && point.z < 1 && Math.abs(point.x) <= 1.08 && Math.abs(point.y) <= 1.08;
                        button.style.left = ((point.x * 0.5 + 0.5) * 100) + '%';
                        button.style.top = ((-point.y * 0.5 + 0.5) * 100) + '%';
                        button.style.bottom = 'auto';
                        button.classList.toggle('is-out-of-view', !visible);
                        button.setAttribute('aria-hidden', visible ? 'false' : 'true');
                        button.tabIndex = visible ? 0 : -1;
                    }
                };

                threeState = state;
                publishObjectRefs(clickableObjects.map(function(object) {
                    return object.name || object.userData.hotspotKey || object.uuid || '';
                }));
                setModelStatus(config.strings.modelloaded ?
                    config.strings.modelloaded.replace('{$a}', clickableObjects.length) :
                    '3D room loaded.');
                renderRotation();
                if (roleCharacter) {
                    var animateRole = function() {
                        state.render();
                        window.requestAnimationFrame(animateRole);
                    };
                    window.requestAnimationFrame(animateRole);
                }

                window.addEventListener('resize', function() {
                    var newWidth = Math.max(1, container.clientWidth);
                    var newHeight = Math.max(1, container.clientHeight);
                    camera.aspect = newWidth / newHeight;
                    camera.updateProjectionMatrix();
                    renderer.setSize(newWidth, newHeight);
                    renderRotation();
                });
            };

            var setupUploaded3dRoom = function(THREE, GLTFLoader, container) {
                if (!container || !GLTFLoader || !config.model3durl) {
                    return;
                }

                var width = Math.max(1, container.clientWidth);
                var height = Math.max(1, container.clientHeight);
                var scene = new THREE.Scene();
                scene.background = new THREE.Color(0xe6edf3);

                var camera = new THREE.PerspectiveCamera(62, width / height, 0.1, 1000);
                camera.position.set(0, 1.7, 6);

                var renderer = new THREE.WebGLRenderer({antialias: true});
                renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
                renderer.setSize(width, height);
                if (renderer.xr) {
                    renderer.xr.enabled = true;
                }
                container.appendChild(renderer.domElement);

                scene.add(new THREE.HemisphereLight(0xffffff, 0x68737d, 1.7));
                var keyLight = new THREE.DirectionalLight(0xffffff, 1.4);
                keyLight.position.set(-3, 5, 4);
                scene.add(keyLight);

                var floor = new THREE.Mesh(
                    new THREE.PlaneGeometry(20, 20),
                    new THREE.MeshStandardMaterial({color: 0x9aa8a0, roughness: 0.9})
                );
                floor.rotation.x = -Math.PI / 2;
                floor.position.y = -0.02;
                scene.add(floor);

                var modelRoot = new THREE.Group();
                scene.add(modelRoot);
                var roleCharacter = null;
                if (config.rolecharacter && config.rolecharacter.enabled) {
                    roleCharacter = createRoleCharacter(THREE, GLTFLoader, getRolePosition({x: -2.2, y: 0, z: -2.6}), 1);
                    scene.add(roleCharacter);
                }
                var raycaster = new THREE.Raycaster();
                var pointer = new THREE.Vector2();
                var setPointerFromEvent = function(event) {
                    var rect = renderer.domElement.getBoundingClientRect();
                    pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
                    pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
                    raycaster.setFromCamera(pointer, camera);
                };
                var findHotspotButtonForObject = function(object) {
                    var current = object;
                    while (current) {
                        var reference = current.name || current.uuid || '';
                        if (reference) {
                            var found = null;
                            hotspots.forEach(function(button) {
                                if ((button.getAttribute('data-hotspot-objectref') || '') === reference) {
                                    found = button;
                                }
                            });
                            if (found) {
                                return found;
                            }
                        }
                        current = current.parent;
                    }
                    return null;
                };
                var activateHit = function(hit) {
                    if (!hit) {
                        return;
                    }
                    if (hit.object.userData.roleCharacter) {
                        openRoleCharacter();
                        return;
                    }
                    var button = findHotspotButtonForObject(hit.object);
                    if (button) {
                        button.click();
                    }
                };

                renderer.domElement.addEventListener('click', function(event) {
                    setPointerFromEvent(event);
                    var roleHit = roleCharacter ? raycaster.intersectObject(roleCharacter, true)[0] : null;
                    var modelHit = modelRoot.children.length ? raycaster.intersectObjects(modelRoot.children, true)[0] : null;
                    activateHit(roleHit || modelHit);
                });

                var loader = new GLTFLoader();
                setModelStatus(config.strings.loadingmodel || 'Loading 3D model...');
                loader.load(config.model3durl, function(gltf) {
                    modelRoot.clear();
                    var model = gltf.scene || gltf.scenes[0];
                    if (!model) {
                        return;
                    }

                    var box = new THREE.Box3().setFromObject(model);
                    var size = box.getSize(new THREE.Vector3());
                    var center = box.getCenter(new THREE.Vector3());
                    var largest = Math.max(size.x, size.y, size.z, 0.001);
                    var scale = 3.6 / largest;

                    model.scale.setScalar(scale);
                    model.position.set(-center.x * scale, -center.y * scale, -center.z * scale);
                    modelRoot.add(model);
                    var refs = [];
                    var triangleCount = 0;
                    model.traverse(function(object) {
                        if (object.name) {
                            refs.push(object.name);
                        }
                        if (object.isMesh && object.geometry) {
                            var position = object.geometry.getAttribute ? object.geometry.getAttribute('position') : null;
                            if (position) {
                                triangleCount += Math.floor(position.count / 3);
                            }
                            refs.push(object.name || object.uuid);
                        }
                    });
                    publishObjectRefs(refs);
                    var loaded = config.strings.modelloaded ?
                        config.strings.modelloaded.replace('{$a}', refs.length) :
                        '3D model loaded.';
                    if (triangleCount > 100000) {
                        setModelStatus((config.strings.modelbigwarning || 'Large 3D model: {$a} triangles')
                            .replace('{$a}', triangleCount), true);
                    } else {
                        setModelStatus(loaded);
                    }
                    renderRotation();
                }, null, function() {
                    // The teacher-facing Moodle file manager keeps the source file visible for correction.
                    setModelStatus(config.strings.model3dmissing || 'No uploaded 3D model file has been added yet.', true);
                });

                var controller = null;
                var controllerMatrix = new THREE.Matrix4();
                var state = {
                    lon: 180,
                    bounds: {
                        minX: -8,
                        maxX: 8,
                        minZ: -8,
                        maxZ: 8
                    },
                    render: function() {
                        if (roleCharacter && roleCharacter.userData.animateRole) {
                            roleCharacter.userData.animateRole(Date.now());
                        }
                        var yaw = THREE.MathUtils.degToRad(state.lon - 180);
                        if (!(renderer.xr && renderer.xr.isPresenting)) {
                            camera.lookAt(
                                camera.position.x + Math.sin(yaw) * 10,
                                1.35,
                                camera.position.z - Math.cos(yaw) * 10
                            );
                        }
                        renderer.render(scene, camera);
                    },
                    enterWebXR: function() {
                        if (!navigator.xr || !renderer.xr) {
                            return Promise.reject(new Error('WebXR unavailable'));
                        }
                        if (!controller) {
                            controller = renderer.xr.getController(0);
                            controller.addEventListener('selectstart', function() {
                                controllerMatrix.identity().extractRotation(controller.matrixWorld);
                                raycaster.ray.origin.setFromMatrixPosition(controller.matrixWorld);
                                raycaster.ray.direction.set(0, 0, -1).applyMatrix4(controllerMatrix);
                                var roleHit = roleCharacter ? raycaster.intersectObject(roleCharacter, true)[0] : null;
                                var modelHit = modelRoot.children.length ? raycaster.intersectObjects(modelRoot.children, true)[0] : null;
                                activateHit(roleHit || modelHit);
                            });
                            scene.add(controller);
                        }
                        return navigator.xr.requestSession('immersive-vr', {
                            optionalFeatures: ['local-floor', 'bounded-floor']
                        }).then(function(session) {
                            return Promise.resolve(renderer.xr.setSession(session)).then(function() {
                                renderer.setAnimationLoop(function() {
                                    state.render();
                                });
                                session.addEventListener('end', function() {
                                    renderer.setAnimationLoop(null);
                                    state.render();
                                });
                                return session;
                            });
                        });
                    },
                    moveForward: function(amount) {
                        var yaw = THREE.MathUtils.degToRad(state.lon - 180);
                        camera.position.x = clamp(camera.position.x + Math.sin(yaw) * amount, state.bounds.minX, state.bounds.maxX);
                        camera.position.z = clamp(camera.position.z - Math.cos(yaw) * amount, state.bounds.minZ, state.bounds.maxZ);
                        renderRotation();
                    },
                    capturePosition: function(event) {
                        setPointerFromEvent(event);
                        var modelHit = modelRoot.children.length ? raycaster.intersectObjects(modelRoot.children, true)[0] : null;
                        var floorHit = raycaster.intersectObject(floor, false)[0];
                        var hit = modelHit || floorHit;
                        if (hit && hit.object) {
                            hit.point.objectref = hit.object.name || hit.object.uuid || '';
                        }
                        return hit ? hit.point : null;
                    },
                    projectHotspots: function(buttons) {
                        buttons.forEach(function(button) {
                            var x = parseFloat(button.getAttribute('data-object-x'));
                            var y = parseFloat(button.getAttribute('data-object-y'));
                            var z = parseFloat(button.getAttribute('data-object-z'));
                            if (isNaN(x) || isNaN(y) || isNaN(z)) {
                                return;
                            }

                            var point = new THREE.Vector3(x, y, z).project(camera);
                            var visible = point.z > -1 && point.z < 1 && Math.abs(point.x) <= 1.08 && Math.abs(point.y) <= 1.08;
                            button.style.left = ((point.x * 0.5 + 0.5) * 100) + '%';
                            button.style.top = ((-point.y * 0.5 + 0.5) * 100) + '%';
                            button.classList.toggle('is-out-of-view', !visible);
                            button.setAttribute('aria-hidden', visible ? 'false' : 'true');
                            button.tabIndex = visible ? 0 : -1;
                        });
                    },
                    projectRoleCharacter: function(button) {
                        if (!roleCharacter) {
                            return;
                        }

                        var point = new THREE.Vector3(
                            roleCharacter.position.x,
                            roleCharacter.position.y + roleCharacter.userData.roleAnchorYOffset,
                            roleCharacter.position.z
                        ).project(camera);
                        var visible = point.z > -1 && point.z < 1 && Math.abs(point.x) <= 1.08 && Math.abs(point.y) <= 1.08;
                        button.style.left = ((point.x * 0.5 + 0.5) * 100) + '%';
                        button.style.top = ((-point.y * 0.5 + 0.5) * 100) + '%';
                        button.style.bottom = 'auto';
                        button.classList.toggle('is-out-of-view', !visible);
                        button.setAttribute('aria-hidden', visible ? 'false' : 'true');
                        button.tabIndex = visible ? 0 : -1;
                    }
                };

                threeState = state;
                renderRotation();
                if (roleCharacter) {
                    var animateRole = function() {
                        state.render();
                        window.requestAnimationFrame(animateRole);
                    };
                    window.requestAnimationFrame(animateRole);
                }

                window.addEventListener('resize', function() {
                    var newWidth = Math.max(1, container.clientWidth);
                    var newHeight = Math.max(1, container.clientHeight);
                    camera.aspect = newWidth / newHeight;
                    camera.updateProjectionMatrix();
                    renderer.setSize(newWidth, newHeight);
                    renderRotation();
                });
            };

            if (config.threeurl && typeof window !== 'undefined' && window.WebGLRenderingContext) {
                if (roomMode === 'uploaded3d' || (config.rolecharacter && config.rolecharacter.modelurl)) {
                    Promise.all([import(config.threeurl), import(config.gltfloaderurl)]).then(function(modules) {
                        setupThreeViewer(modules[0], modules[1]);
                        return modules;
                    }).catch(function() {
                        // Keep the activity usable if model loading support is unavailable.
                    });
                } else {
                    import(config.threeurl).then(function(THREE) {
                        setupThreeViewer(THREE, null);
                        return THREE;
                    }).catch(function() {
                        // Keep the CSS panorama fallback if WebGL or module loading fails.
                    });
                }
            }

            root.querySelectorAll('[data-action="pan-left"]').forEach(function(button) {
                button.addEventListener('click', function() {
                    rotateBy(15);
                });
            });
            root.querySelectorAll('[data-action="pan-right"]').forEach(function(button) {
                button.addEventListener('click', function() {
                    rotateBy(-15);
                });
            });
            root.querySelectorAll('[data-action="move-forward"]').forEach(function(button) {
                button.addEventListener('click', function() {
                    moveBy(0.55);
                });
            });
            root.querySelectorAll('[data-action="move-back"]').forEach(function(button) {
                button.addEventListener('click', function() {
                    moveBy(-0.55);
                });
            });

            var dragging = false;
            var lastX = 0;

            stage.addEventListener('pointerdown', function(event) {
                if (event.target.closest('button') || event.target.closest('.flwvrroom-author-tools')) {
                    return;
                }
                dragging = true;
                lastX = event.clientX;
                stage.classList.add('is-dragging');
                if (stage.setPointerCapture) {
                    stage.setPointerCapture(event.pointerId);
                }
            });

            stage.addEventListener('pointermove', function(event) {
                if (!dragging) {
                    return;
                }
                var delta = lastX - event.clientX;
                lastX = event.clientX;
                rotateBy(delta * 0.08);
                event.preventDefault();
            });

            var stopDragging = function(event) {
                dragging = false;
                stage.classList.remove('is-dragging');
                if (event && stage.releasePointerCapture) {
                    try {
                        stage.releasePointerCapture(event.pointerId);
                    } catch (error) {
                        // Pointer capture can already be released by the browser.
                    }
                }
            };

            stage.addEventListener('pointerup', stopDragging);
            stage.addEventListener('pointercancel', stopDragging);
            stage.addEventListener('lostpointercapture', stopDragging);

            stage.addEventListener('keydown', function(event) {
                if (event.key === 'ArrowLeft') {
                    rotateBy(15);
                    event.preventDefault();
                }
                if (event.key === 'ArrowRight') {
                    rotateBy(-15);
                    event.preventDefault();
                }
                if (event.key === 'ArrowUp' || event.key === 'w' || event.key === 'W') {
                    moveBy(0.55);
                    event.preventDefault();
                }
                if (event.key === 'ArrowDown' || event.key === 's' || event.key === 'S') {
                    moveBy(-0.55);
                    event.preventDefault();
                }
                if (event.key === 'a' || event.key === 'A') {
                    rotateBy(15);
                    event.preventDefault();
                }
                if (event.key === 'd' || event.key === 'D') {
                    rotateBy(-15);
                    event.preventDefault();
                }
            });

            var helperActive = false;
            var helperButton = root.querySelector('[data-action="toggle-position-helper"]');
            var helperStatus = root.querySelector('[data-region="position-helper-status"]');
            var helperTarget = root.querySelector('[data-region="position-helper-target"]');
            var helperOutput = root.querySelector('[data-region="position-helper-output"]');
            var helperHotspotKey = root.querySelector('[data-region="position-helper-hotspot-key"]');
            var helperHotspotLabel = root.querySelector('[data-region="position-helper-hotspot-label"]');
            var helperHotspotScore = root.querySelector('[data-region="position-helper-hotspot-score"]');
            var helperVisualPlaceButton = root.querySelector('[data-action="visual-place-hotspot"]');

            var cleanHelperPart = function(value, fallback) {
                value = String(value || fallback || '').replace(/[|\r\n]/g, ' ').trim();
                return value || fallback || '';
            };
            var screenToWorldX = function(screenX) {
                return wrap(rotation + ((screenX - 50) * (visibleSpan / 2) / 50));
            };
            var hotspotLineFromScenePoint = function(event, currentLine) {
                var parts = hotspotPartsFromLine(currentLine);
                var rect = stage.getBoundingClientRect();
                var screenX = clamp(((event.clientX - rect.left) / rect.width) * 100, 0, 100);
                var screenY = clamp(((event.clientY - rect.top) / rect.height) * 100, 0, 100);
                var point = null;

                if ((roomMode === 'builtin3d' || roomMode === 'uploaded3d') && threeState && threeState.capturePosition) {
                    point = threeState.capturePosition(event);
                }

                if (point) {
                    parts[3] = screenX.toFixed(1);
                    parts[4] = screenY.toFixed(1);
                    parts[7] = point.x.toFixed(2);
                    parts[8] = point.y.toFixed(2);
                    parts[9] = point.z.toFixed(2);
                    parts[11] = point.objectref || parts[11] || '';
                } else {
                    parts[3] = screenToWorldX(screenX).toFixed(1);
                    parts[4] = screenY.toFixed(1);
                }

                return {
                    line: parts.join('|'),
                    parts: parts,
                    raw2d: parts[3] + '|' + parts[4],
                    raw3d: point ? parts[7] + '|' + parts[8] + '|' + parts[9] : '',
                    objectref: point ? (point.objectref || '') : ''
                };
            };

            if (helperButton && helperStatus) {
                helperButton.addEventListener('click', function() {
                    helperActive = !helperActive;
                    root.classList.toggle('is-position-helper-active', helperActive);
                    helperStatus.textContent = helperActive ?
                        (config.strings.positionhelperactive || 'Click the room to copy x/y') :
                        (config.strings.positionhelperidle || 'Click to capture x/y');
                });

                if (helperVisualPlaceButton) {
                    helperVisualPlaceButton.addEventListener('click', function() {
                        helperActive = true;
                        visualPlacementPending = true;
                        root.classList.add('is-position-helper-active');
                        if (helperTarget) {
                            helperTarget.value = 'hotspot';
                        }
                        helperStatus.textContent = config.strings.visualeditorselect ||
                            'Select a point in the room for this hotspot.';
                    });
                }

                var draggedHotspot = null;
                var updateDraggedHotspot = function(event) {
                    if (!draggedHotspot) {
                        return;
                    }
                    var field = getEditorField('customhotspots');
                    if (field && selectedHotspotIndex >= 0) {
                        var lines = String(field.value || '').split(/\r?\n/);
                        if (selectedHotspotIndex < lines.length) {
                            var result = hotspotLineFromScenePoint(event, lines[selectedHotspotIndex]);
                            replaceEditorLine('customhotspots', selectedHotspotIndex, result.line);
                            applyHotspotPartsToButton(draggedHotspot, result.parts, hotspotPartsFromLine(lines[selectedHotspotIndex])[0]);
                            setHotspotBuilderValue('position2d', result.raw2d);
                            if (result.raw3d) {
                                setHotspotBuilderValue('position3d', result.raw3d);
                            }
                            if (result.objectref) {
                                setHotspotBuilderValue('objectref', result.objectref);
                            }
                            renderRotation();
                        }
                    }
                };

                stage.addEventListener('pointerdown', function(event) {
                    if (!helperActive || (helperTarget && helperTarget.value !== 'hotspot')) {
                        return;
                    }
                    var button = event.target.closest('[data-hotspot]');
                    if (!button) {
                        return;
                    }
                    var key = button.getAttribute('data-hotspot') || '';
                    var field = getEditorField('customhotspots');
                    var matched = false;
                    if (field && key) {
                        String(field.value || '').split(/\r?\n/).some(function(line, index) {
                            if ((line.split('|')[0] || '') === key) {
                                selectedHotspotIndex = index;
                                loadHotspotLineToBuilder(line);
                                renderHotspotPreview();
                                matched = true;
                                return true;
                            }
                            return false;
                        });
                        if (!matched) {
                            var fallbackLine = [
                                key,
                                button.getAttribute('data-hotspot-label') || key,
                                button.getAttribute('data-score') || 10,
                                button.getAttribute('data-world-x') || 50,
                                button.getAttribute('data-world-y') || 50,
                                button.getAttribute('data-hotspot-description') || '',
                                button.getAttribute('data-hotspot-audio') || '',
                                button.getAttribute('data-object-x') || '',
                                button.getAttribute('data-object-y') || '',
                                button.getAttribute('data-object-z') || '',
                                button.getAttribute('data-hotspot-kpcodes') || '',
                                button.getAttribute('data-hotspot-objectref') || ''
                            ].join('|');
                            appendEditorLine('customhotspots', fallbackLine);
                            selectedHotspotIndex = splitLines(field.value).length - 1;
                            loadHotspotLineToBuilder(fallbackLine);
                            renderHotspotPreview();
                        }
                    }
                    draggedHotspot = button;
                    button.setPointerCapture(event.pointerId);
                    event.preventDefault();
                    event.stopPropagation();
                }, true);

                stage.addEventListener('pointermove', function(event) {
                    if (!draggedHotspot) {
                        return;
                    }
                    updateDraggedHotspot(event);
                    event.preventDefault();
                    event.stopPropagation();
                }, true);

                stage.addEventListener('pointerup', function(event) {
                    if (!draggedHotspot) {
                        return;
                    }
                    updateDraggedHotspot(event);
                    draggedHotspot = null;
                    renderHotspotPreview();
                    if (helperStatus) {
                        helperStatus.textContent = config.strings.visualeditorupdated ||
                            'Selected hotspot updated from the scene.';
                    }
                    event.preventDefault();
                    event.stopPropagation();
                }, true);

                stage.addEventListener('click', function(event) {
                    if ((!helperActive && !visualPlacementPending) ||
                            event.target.closest('button') ||
                            event.target.closest('.flwvrroom-author-tools') ||
                            event.target.closest('.flwvrroom-hotspot-card') ||
                            event.target.closest('.flwvrroom-role-card')) {
                        return;
                    }

                    var target = helperTarget ? helperTarget.value : 'raw';
                    var baseLine = hotspotLineFromBuilder();
                    var placed = hotspotLineFromScenePoint(event, baseLine);
                    var raw3d = placed.raw3d;
                    var raw2d = placed.raw2d;
                    var objectref = placed.objectref;
                    var value = raw3d || raw2d;
                    var copied = raw3d ?
                        (config.strings.positionhelpercopied3d || 'Copied 3D x/y/z: {$a}') :
                        (config.strings.positionhelpercopied || 'Copied x/y: {$a}');
                    if (target === 'role') {
                        if (!raw3d) {
                            helperStatus.textContent = config.strings.positionhelperroleneeds3d ||
                                'Role character placement needs a 3D room click.';
                            event.preventDefault();
                            event.stopPropagation();
                            return;
                        }
                        value = raw3d;
                        copied = config.strings.positionhelpercopiedrole || 'Copied role character position: {$a}';
                        var rolePositionField = getEditorField('rolecharacterposition');
                        if (rolePositionField) {
                            rolePositionField.value = value;
                        }
                    } else if (target === 'hotspot') {
                        var key = cleanHelperPart(helperHotspotKey ? helperHotspotKey.value : '', 'newhotspot');
                        var label = cleanHelperPart(helperHotspotLabel ? helperHotspotLabel.value : '', 'New hotspot');
                        var score = parseInt(helperHotspotScore ? helperHotspotScore.value : '10', 10);
                        score = isNaN(score) ? 10 : clamp(score, 0, 100);
                        placed.parts[0] = key;
                        placed.parts[1] = label;
                        placed.parts[2] = score;
                        value = placed.parts.join('|');
                        var builderPosition2d = root.querySelector('[data-hotspot-builder="position2d"]');
                        var builderPosition3d = root.querySelector('[data-hotspot-builder="position3d"]');
                        var builderObjectRef = root.querySelector('[data-hotspot-builder="objectref"]');
                        if (builderPosition2d) {
                            builderPosition2d.value = raw2d;
                        }
                        if (builderPosition3d && raw3d) {
                            builderPosition3d.value = raw3d;
                        }
                        if (builderObjectRef && objectref) {
                            builderObjectRef.value = objectref;
                        }
                        copied = config.strings.positionhelpercopiedhotspot || 'Copied custom hotspot line: {$a}';
                        var oldLineForLive = '';
                        var liveField = getEditorField('customhotspots');
                        if (liveField && selectedHotspotIndex >= 0) {
                            oldLineForLive = String(liveField.value || '').split(/\r?\n/)[selectedHotspotIndex] || '';
                        }
                        if (typeof selectedHotspotIndex !== 'undefined' &&
                                replaceEditorLine('customhotspots', selectedHotspotIndex, value)) {
                            syncLiveHotspotFromLine(oldLineForLive, value);
                            copied = config.strings.visualeditorupdated || 'Selected hotspot updated from the scene.';
                        } else {
                            appendEditorLine('customhotspots', value);
                            createLiveHotspotFromLine(value);
                            copied = config.strings.visualeditorcreated || 'Hotspot created from the scene.';
                        }
                        if (typeof renderHotspotPreview === 'function') {
                            renderHotspotPreview();
                        }
                        renderRotation();
                    }

                    helperStatus.textContent = copied.replace('{$a}', value);
                    visualPlacementPending = false;
                    if (helperOutput) {
                        helperOutput.value = value;
                        helperOutput.select();
                    }
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(value).catch(function() {
                            return false;
                        });
                    }

                    event.preventDefault();
                    event.stopPropagation();
                }, true);
            }
        }

        var hotspotCard = root.querySelector('[data-region="hotspot-card"]');
        var hotspotTitle = root.querySelector('[data-region="hotspot-title"]');
        var hotspotDescription = root.querySelector('[data-region="hotspot-description"]');
        var hotspotAudio = root.querySelector('[data-region="hotspot-audio"]');
        var closeHotspotCard = root.querySelector('[data-action="close-hotspot-card"]');
        var nextStepRegion = root.querySelector('[data-region="next-step"]');

        var updateMissionProgress = function() {
            var remainingHotspots = 0;
            root.querySelectorAll('[data-progress-hotspot]').forEach(function(item) {
                var key = item.getAttribute('data-progress-hotspot');
                var hotspot = root.querySelector('[data-hotspot="' + key + '"]');
                var complete = !!(hotspot && hotspot.classList.contains('is-complete'));
                item.classList.toggle('is-complete', complete);
                if (!complete) {
                    remainingHotspots++;
                }
            });

            root.querySelectorAll('[data-progress-speaking]').forEach(function(item) {
                item.classList.toggle('is-complete', root.getAttribute('data-speaking-complete') === '1');
            });
            root.querySelectorAll('[data-progress-role]').forEach(function(item) {
                item.classList.toggle('is-complete', root.getAttribute('data-role-complete') === '1');
            });

            if (!nextStepRegion) {
                return;
            }
            if (remainingHotspots > 0) {
                nextStepRegion.textContent = config.strings.nextstephotspot || 'Explore the remaining hotspots.';
            } else if (root.getAttribute('data-speaking-complete') !== '1') {
                nextStepRegion.textContent = config.strings.nextstepspeaking || 'Record your speaking answer.';
            } else if (root.querySelector('[data-progress-role]') && root.getAttribute('data-role-complete') !== '1') {
                nextStepRegion.textContent = config.strings.nextsteprole || 'Complete the role-play conversation.';
            } else {
                nextStepRegion.textContent = config.strings.nextstepsave || 'Save your attempt.';
            }
        };

        var hideHotspotCard = function() {
            if (!hotspotCard) {
                return;
            }
            hotspotCard.hidden = true;
            root.classList.remove('is-hotspot-card-open');
            if (hotspotAudio) {
                hotspotAudio.pause();
                hotspotAudio.removeAttribute('src');
                hotspotAudio.hidden = true;
            }
        };

        var showHotspotCard = function(button) {
            if (!hotspotCard || !hotspotTitle || !hotspotDescription) {
                return;
            }

            var label = button.getAttribute('data-hotspot-label') || button.textContent || '';
            var description = button.getAttribute('data-hotspot-description') || '';
            var audioUrl = button.getAttribute('data-hotspot-audio') || '';

            hotspotTitle.textContent = label;
            hotspotDescription.textContent = description || label;
            if (hotspotAudio) {
                if (audioUrl) {
                    hotspotAudio.src = audioUrl;
                    hotspotAudio.hidden = false;
                    hotspotAudio.load();
                } else {
                    hotspotAudio.pause();
                    hotspotAudio.removeAttribute('src');
                    hotspotAudio.hidden = true;
                }
            }

            hotspotCard.hidden = false;
            root.classList.add('is-hotspot-card-open');
        };

        if (hotspotCard) {
            hotspotCard.addEventListener('pointerdown', function(event) {
                event.stopPropagation();
            });
        }

        if (closeHotspotCard) {
            closeHotspotCard.addEventListener('click', hideHotspotCard);
        }

        root.querySelectorAll('[data-hotspot]').forEach(function(button) {
            button.addEventListener('click', function() {
                button.classList.add('is-complete');
                button.setAttribute('aria-pressed', 'true');
                showHotspotCard(button);
                updateScore(root, config.passinggrade, config.maxgrade);
                updateMissionProgress();
            });
        });

        root.querySelectorAll('input[type=radio]').forEach(function(input) {
            input.addEventListener('change', function() {
                updateScore(root, config.passinggrade, config.maxgrade);
                updateMissionProgress();
            });
        });

        var speakingText = '';
        var aiFeedback = '';
        var speakingResults = [];
        var roleSpeakingText = '';
        var roleAiFeedback = '';
        var roleTurnResults = [];
        var roleSpeakingLog = [];
        var roleFeedbackLog = [];
        var roleComplete = false;
        var roleTurns = (config.rolecharacter && config.rolecharacter.turns && config.rolecharacter.turns.length) ?
            config.rolecharacter.turns :
            [{
                line: config.rolecharacter ? (config.rolecharacter.line || '') : '',
                expectedanswer: config.rolecharacter ? (config.rolecharacter.expectedanswer || '') : '',
                score: config.rolecharacter ? (config.rolecharacter.score || 0) : 0,
                kpcodes: config.rolecharacter ? (config.rolecharacter.kpcodes || []) : []
            }];
        var roleAiEnabled = !!(config.rolecharacter && config.rolecharacter.aienabled);
        var roleMaxTurns = roleAiEnabled ? (parseInt(config.rolecharacter.aiturns, 10) || 3) : roleTurns.length;
        var roleTurnIndex = 0;
        var roleEarnedScore = 0;
        var roleCompletedKpcodes = [];
        var roleConversationHistory = [];
        var recorder = null;
        var recordingChunks = [];
        var speakingButton = root.querySelector('[data-action="record-speaking"]');
        var transcriptRegion = root.querySelector('[data-region="speaking-transcript"]');
        var feedbackRegion = root.querySelector('[data-region="speaking-feedback"]');
        var roleCard = root.querySelector('[data-region="role-card"]');
        var closeRoleButton = root.querySelector('[data-action="close-role-card"]');
        var roleSpeakingButton = root.querySelector('[data-action="record-role-speaking"]');
        var roleTranscriptRegion = root.querySelector('[data-region="role-transcript"]');
        var roleFeedbackRegion = root.querySelector('[data-region="role-feedback"]');
        var roleLineRegion = root.querySelector('[data-region="role-line"]');
        var roleTurnProgressRegion = root.querySelector('[data-region="role-turn-progress"]');

        var completionIssues = function(score) {
            var rules = config.completionrules || {};
            var issues = [];
            if (rules.requirehotspots !== false) {
                var missingHotspots = false;
                root.querySelectorAll('[data-hotspot]').forEach(function(button) {
                    if (!button.classList.contains('is-complete')) {
                        missingHotspots = true;
                    }
                });
                if (missingHotspots) {
                    issues.push(config.strings.completionmissinghotspots || 'Complete all hotspots.');
                }
            }
            if (rules.requirespeaking !== false && root.getAttribute('data-speaking-complete') !== '1') {
                issues.push(config.strings.completionmissingspeaking || 'Record your speaking answer.');
            }
            if (rules.requirerole && root.getAttribute('data-role-complete') !== '1') {
                issues.push(config.strings.completionmissingrole || 'Complete the role-play conversation.');
            }
            var minscore = parseInt(rules.minscore, 10);
            if (isNaN(minscore)) {
                minscore = config.passinggrade || 0;
            }
            if (score < minscore) {
                issues.push((config.strings.completionmissingscore || 'Reach at least {$a} points.').replace('{$a}', minscore));
            }
            return issues;
        };

        var normalizeServiceScore = function(value) {
            value = parseFloat(value);
            if (isNaN(value)) {
                return null;
            }
            return clamp(value > 1 ? value / 100 : value, 0, 1);
        };

        var completedHotspotPayload = function() {
            var payload = [];
            root.querySelectorAll('[data-hotspot].is-complete').forEach(function(button) {
                var key = button.getAttribute('data-hotspot') || '';
                payload.push({
                    id: key,
                    title: button.textContent ? button.textContent.trim() : key,
                    kind: key === 'rolecharacter' ? 'roleplay' : 'object3d',
                    completed: true,
                    score: parseInt(button.getAttribute('data-score'), 10) || 0,
                    maxscore: config.maxgrade || 100,
                    position: {
                        x: parseFloat(button.getAttribute('data-object-x')) || 0,
                        y: parseFloat(button.getAttribute('data-object-y')) || 0,
                        z: parseFloat(button.getAttribute('data-object-z')) || 0
                    },
                    objectref: button.getAttribute('data-hotspot-objectref') || '',
                    kpcodes: String(button.getAttribute('data-hotspot-kpcodes') || '').split(',').filter(function(code) {
                        return code.trim() !== '';
                    })
                });
            });
            if (roleComplete) {
                payload.push({
                    id: 'rolecharacter',
                    title: config.rolecharacter ? config.rolecharacter.name || 'Role character' : 'Role character',
                    kind: 'roleplay',
                    completed: true,
                    score: roleEarnedScore || (config.rolecharacter ? config.rolecharacter.score || 0 : 0),
                    maxscore: config.maxgrade || 100,
                    kpcodes: roleCompletedKpcodes.length ? roleCompletedKpcodes :
                        (config.rolecharacter && config.rolecharacter.kpcodes ? config.rolecharacter.kpcodes : [])
                });
            }
            return payload;
        };
        var roleDialogueList = root.querySelector('[data-region="role-dialogue-list"]');
        var roleConversationEntries = [];
        var roleLoggedTurnIndex = -1;
        var roleLoggedLine = '';
        var roomEditorSave = root.querySelector('[data-action="save-room-editor"]');
        var roomEditorStatus = root.querySelector('[data-region="room-editor-status"]');
        var roleTurnAppendButton = root.querySelector('[data-action="append-role-turn"]');
        var hotspotAppendButton = root.querySelector('[data-action="append-hotspot"]');
        var hotspotUpdateButton = root.querySelector('[data-action="update-hotspot"]');
        var hotspotDeleteButton = root.querySelector('[data-action="delete-hotspot"]');
        var hotspotPreview = root.querySelector('[data-region="hotspot-preview"]');
        var roleTurnPreview = root.querySelector('[data-region="role-turn-preview"]');
        var insertKpButton = root.querySelector('[data-action="insert-kp-code"]');
        var kpHelperSelect = root.querySelector('[data-region="kp-helper-select"]');
        var kpHelperTarget = root.querySelector('[data-region="kp-helper-target"]');
        var webxrButton = root.querySelector('[data-action="enter-webxr"]');
        var xrStatus = root.querySelector('[data-region="xr-status"]');
        var scenarioTemplateSelect = root.querySelector('[data-region="scenario-template-select"]');
        var applyScenarioTemplateButton = root.querySelector('[data-action="apply-scenario-template"]');
        var scenarioJsonField = root.querySelector('[data-region="scenario-json"]');
        var exportScenarioButton = root.querySelector('[data-action="export-scenario"]');
        var importScenarioButton = root.querySelector('[data-action="import-scenario"]');
        var objectBrowserSelect = root.querySelector('[data-region="object-browser-select"]');
        var bindObjectRefButton = root.querySelector('[data-action="bind-object-ref"]');
        var visualPlaceHotspotButton = root.querySelector('[data-action="visual-place-hotspot"]');
        var visualPlacementPending = false;
        var selectedHotspotIndex = -1;

        var splitLines = function(value) {
            return String(value || '').split(/\r?\n/).map(function(line) {
                return line.trim();
            }).filter(function(line) {
                return line !== '';
            });
        };

        var splitCodes = function(value) {
            return String(value || '').split(/[,\r\n]+/).map(function(code) {
                return code.trim();
            }).filter(function(code) {
                return code !== '';
            });
        };
        var intOrDefault = function(value, fallback) {
            value = parseInt(value, 10);
            return isNaN(value) ? fallback : value;
        };

        var hotspotBuilderValue = function(name) {
            var field = root.querySelector('[data-hotspot-builder="' + name + '"]');
            return field ? String(field.value || '').trim() : '';
        };

        var setHotspotBuilderValue = function(name, value) {
            var field = root.querySelector('[data-hotspot-builder="' + name + '"]');
            if (field) {
                field.value = value || '';
            }
        };

        var hotspotLineFromBuilder = function() {
            var key = hotspotBuilderValue('key').replace(/[|\r\n]/g, ' ') || 'newhotspot';
            var label = hotspotBuilderValue('label').replace(/[|\r\n]/g, ' ') || key;
            var score = parseInt(hotspotBuilderValue('score'), 10);
            var position2d = hotspotBuilderValue('position2d') || '50|50';
            var position3d = hotspotBuilderValue('position3d');
            var pos2 = position2d.split('|');
            var pos3 = position3d.split('|');
            var description = hotspotBuilderValue('description').replace(/[|\r\n]/g, ' ');
            var audio = hotspotBuilderValue('audio').replace(/[|\r\n]/g, ' ');
            var kpcodes = splitCodes(hotspotBuilderValue('kpcodes')).join(',');
            var objectref = hotspotBuilderValue('objectref').replace(/[|\r\n]/g, ' ');
            var pos3x = parseFloat(pos3[0]);
            var pos3y = parseFloat(pos3[1]);
            var pos3z = parseFloat(pos3[2]);

            return [
                key,
                label,
                isNaN(score) ? 10 : clamp(score, 0, 100),
                parseFloat(pos2[0]) || 50,
                parseFloat(pos2[1]) || 50,
                description,
                audio,
                isNaN(pos3x) ? '' : pos3x,
                isNaN(pos3y) ? '' : pos3y,
                isNaN(pos3z) ? '' : pos3z,
                kpcodes,
                objectref
            ].join('|');
        };

        var loadHotspotLineToBuilder = function(line) {
            var parts = String(line || '').split('|');
            setHotspotBuilderValue('key', parts[0] || '');
            setHotspotBuilderValue('label', parts[1] || '');
            setHotspotBuilderValue('score', parts[2] || '10');
            setHotspotBuilderValue('position2d', (parts[3] || '50') + '|' + (parts[4] || '50'));
            setHotspotBuilderValue('description', parts[5] || '');
            setHotspotBuilderValue('audio', parts[6] || '');
            setHotspotBuilderValue('position3d', [parts[7] || '', parts[8] || '', parts[9] || ''].join('|').replace(/^\|+|\|+$/g, ''));
            setHotspotBuilderValue('kpcodes', parts[10] || '');
            setHotspotBuilderValue('objectref', parts[11] || '');
        };

        var renderHotspotPreview = function() {
            var field = getEditorField('customhotspots');
            if (!hotspotPreview || !field) {
                return;
            }
            var lines = splitLines(field.value);
            hotspotPreview.innerHTML = '';
            if (!lines.length) {
                hotspotPreview.textContent = config.strings.nohotspots || 'No custom hotspots yet.';
                return;
            }
            lines.forEach(function(line, index) {
                var parts = line.split('|');
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-secondary btn-sm';
                button.textContent = (parts[1] || parts[0] || 'Hotspot') + ' / ' + (parts[2] || '0');
                if (index === selectedHotspotIndex) {
                    button.classList.add('active');
                }
                button.addEventListener('click', function() {
                    selectedHotspotIndex = index;
                    loadHotspotLineToBuilder(line);
                    if (helperHotspotKey) {
                        helperHotspotKey.value = parts[0] || 'newhotspot';
                    }
                    if (helperHotspotLabel) {
                        helperHotspotLabel.value = parts[1] || parts[0] || 'New hotspot';
                    }
                    if (helperHotspotScore) {
                        helperHotspotScore.value = parts[2] || '10';
                    }
                    renderHotspotPreview();
                });
                hotspotPreview.appendChild(button);
            });
        };

        var renderRoleTurnPreview = function() {
            var field = getEditorField('roleturns');
            if (!roleTurnPreview || !field) {
                return;
            }
            var lines = splitLines(field.value);
            roleTurnPreview.innerHTML = '';
            if (!lines.length) {
                roleTurnPreview.textContent = config.strings.noroleturns || 'No role turns yet.';
                return;
            }
            lines.forEach(function(line, index) {
                var parts = line.split('|');
                var item = document.createElement('div');
                item.className = 'flwvrroom-editor-preview-row';
                item.textContent = (index + 1) + '. ' + (parts[0] || '') + ' -> ' + (parts[1] || '') +
                    ' / ' + (parts[2] || '0') + ' / ' + (parts[3] || '');
                roleTurnPreview.appendChild(item);
            });
        };

        var insertKpCode = function() {
            if (!kpHelperSelect) {
                return;
            }
            var target = kpHelperTarget ? kpHelperTarget.value : 'activity';
            var code = kpHelperSelect.value;
            var field = null;
            if (target === 'activity') {
                field = getEditorField('kpcodes');
            } else if (target === 'hotspot') {
                field = root.querySelector('[data-hotspot-builder="kpcodes"]');
            } else if (target === 'turn') {
                field = root.querySelector('[data-builder-field="kpcodes"]');
            } else {
                field = getEditorField('rolekpcodes');
            }
            if (!field || !code) {
                return;
            }
            var codes = splitCodes(field.value);
            if (codes.indexOf(code) === -1) {
                codes.push(code);
            }
            field.value = codes.join(target === 'activity' || target === 'role' ? "\n" : ',');
            field.dispatchEvent(new Event('input', {bubbles: true}));
        };

        var setEditorValue = function(name, value) {
            var field = getEditorField(name);
            if (!field) {
                return;
            }
            if (field.type === 'checkbox') {
                field.checked = !!value;
            } else {
                field.value = value || '';
            }
            field.dispatchEvent(new Event('input', {bubbles: true}));
            field.dispatchEvent(new Event('change', {bubbles: true}));
        };

        var applyScenarioData = function(data) {
            setEditorValue('custommissiontitle', data.missiontitle || '');
            setEditorValue('custommissiontext', data.missiontext || '');
            setEditorValue('customquizquestion', data.quizquestion || '');
            setEditorValue('customanswers', data.answers || '');
            setEditorValue('customhotspots', data.hotspots || '');
            setEditorValue('kpcodes', data.kpcodes || '');
            setEditorValue('rolecharacterline', data.rolecharacterline || '');
            setEditorValue('roleexpectedanswer', data.roleexpectedanswer || '');
            setEditorValue('rolekpcodes', data.rolekpcodes || data.kpcodes || '');
            setEditorValue('rolescore', data.rolescore || 20);
            setEditorValue('roleturns', data.roleturns || '');
            if (typeof data.roleaienabled !== 'undefined') {
                setEditorValue('roleaienabled', !!data.roleaienabled);
            }
            setEditorValue('roleaiturns', data.roleaiturns || 3);
            setEditorValue('roleaipersonality', data.roleaipersonality || '');
            setEditorValue('roleaidifficulty', data.roleaidifficulty || 'friendly');
            setEditorValue('roleaitargetpattern', data.roleaitargetpattern || '');
            setEditorValue('roleaimaxretries',
                typeof data.roleaimaxretries === 'undefined' ? 1 : data.roleaimaxretries);
            if (data.completionrules) {
                setEditorValue('completionrequirehotspots', !!data.completionrules.requirehotspots);
                setEditorValue('completionrequirespeaking', !!data.completionrules.requirespeaking);
                setEditorValue('completionrequirerole', !!data.completionrules.requirerole);
                setEditorValue('completionminscore', data.completionrules.minscore || config.passinggrade || 70);
            }
            selectedHotspotIndex = -1;
            renderHotspotPreview();
            renderRoleTurnPreview();
        };

        var editorScenarioData = function() {
            var value = function(name) {
                var field = getEditorField(name);
                if (field && field.type === 'checkbox') {
                    return field.checked;
                }
                return field ? field.value : '';
            };
            return {
                missiontitle: value('custommissiontitle'),
                missiontext: value('custommissiontext'),
                quizquestion: value('customquizquestion'),
                answers: value('customanswers'),
                hotspots: value('customhotspots'),
                kpcodes: value('kpcodes'),
                rolecharacterline: value('rolecharacterline'),
                roleexpectedanswer: value('roleexpectedanswer'),
                rolekpcodes: value('rolekpcodes'),
                rolescore: parseInt(value('rolescore'), 10) || 20,
                roleturns: value('roleturns'),
                roleaienabled: value('roleaienabled'),
                roleaiturns: parseInt(value('roleaiturns'), 10) || 3,
                roleaipersonality: value('roleaipersonality'),
                roleaidifficulty: value('roleaidifficulty') || 'friendly',
                roleaitargetpattern: value('roleaitargetpattern'),
                roleaimaxretries: intOrDefault(value('roleaimaxretries'), 1),
                completionrules: {
                    requirehotspots: value('completionrequirehotspots'),
                    requirespeaking: value('completionrequirespeaking'),
                    requirerole: value('completionrequirerole'),
                    minscore: intOrDefault(value('completionminscore'), config.passinggrade || 70)
                }
            };
        };

        var applyScenarioTemplate = function() {
            if (!scenarioTemplateSelect) {
                return;
            }
            var data = {};
            try {
                data = JSON.parse(scenarioTemplateSelect.value || '{}');
            } catch (error) {
                if (roomEditorStatus) {
                    roomEditorStatus.textContent = config.strings.templateapplyfailed ||
                        'The selected scenario template could not be read.';
                }
                return;
            }

            applyScenarioData(data);
            if (roomEditorStatus) {
                roomEditorStatus.textContent = config.strings.templateapplied ||
                    'Scenario template applied. Save the editor to keep it.';
            }
        };

        var currentRoleTurn = function() {
            return roleTurns[Math.min(roleTurnIndex, roleTurns.length - 1)] || roleTurns[0] || {};
        };

        var updateRoleSpeakingExport = function() {
            roleSpeakingText = roleConversationEntries.map(function(entry) {
                return entry.speaker + ': ' + entry.text;
            }).join("\n");
        };

        var renderRoleConversation = function() {
            if (!roleDialogueList) {
                return;
            }

            roleDialogueList.innerHTML = '';
            roleConversationEntries.forEach(function(entry) {
                var item = document.createElement('div');
                item.className = 'flwvrroom-role-dialogue-item flwvrroom-role-dialogue-' + entry.type;

                var speaker = document.createElement('strong');
                speaker.textContent = entry.speaker;
                item.appendChild(speaker);

                var text = document.createElement('span');
                text.textContent = entry.text;
                item.appendChild(text);

                roleDialogueList.appendChild(item);
            });
        };

        var appendRoleConversation = function(speaker, text, type) {
            text = String(text || '').trim();
            if (!text) {
                return;
            }
            roleConversationEntries.push({
                speaker: speaker,
                text: text,
                type: type || 'character'
            });
            updateRoleSpeakingExport();
            renderRoleConversation();
        };

        var ensureCurrentRoleLineLogged = function() {
            var turn = currentRoleTurn();
            var line = String(turn.line || (config.rolecharacter ? config.rolecharacter.line : '') || '').trim();
            if (!line || (roleLoggedTurnIndex === roleTurnIndex && roleLoggedLine === line)) {
                return;
            }
            appendRoleConversation(config.rolecharacter.name || 'Character', line, 'character');
            roleLoggedTurnIndex = roleTurnIndex;
            roleLoggedLine = line;
        };

        var renderRoleTurn = function() {
            var turn = currentRoleTurn();
            if (roleLineRegion) {
                roleLineRegion.textContent = turn.line || (config.rolecharacter ? config.rolecharacter.line : '');
            }
            if (roleTurnProgressRegion && roleMaxTurns > 1) {
                roleTurnProgressRegion.textContent = (config.strings.roleturnprogress || 'Turn {$a}')
                    .replace('{$a}', (roleTurnIndex + 1) + '/' + roleMaxTurns);
            } else if (roleTurnProgressRegion) {
                roleTurnProgressRegion.textContent = '';
            }
            ensureCurrentRoleLineLogged();
        };
        renderRoleTurn();

        var requestAiWaiterLine = function(turn, learnerReply) {
            if (!roleAiEnabled || !config.rolecharacter) {
                return Promise.resolve('');
            }

            if (roleFeedbackRegion) {
                roleFeedbackRegion.textContent = config.strings.aiwaiterthinking || 'The AI waiter is thinking...';
            }

            return Ajax.call([{
                methodname: 'mod_flwvrroom_role_waiter',
                args: {
                    cmid: config.cmid,
                    character: config.rolecharacter.name || 'Waiter',
                    role: config.rolecharacter.role || 'Cafe waiter',
                    scenario: config.scenario || '',
                    cefrlevel: config.cefrlevel || '',
                    currentline: turn.line || '',
                    learnerreply: learnerReply || '',
                    history: roleConversationHistory.join("\n"),
                    personality: config.rolecharacter.aipersonality || '',
                    difficulty: config.rolecharacter.aidifficulty || 'friendly',
                    targetpattern: config.rolecharacter.aitargetpattern || '',
                    maxretries: intOrDefault(config.rolecharacter.aimaxretries, 1)
                }
            }])[0].then(function(response) {
                if (response.status && response.line) {
                    return response.line;
                }
                if (roleFeedbackRegion) {
                    roleFeedbackRegion.textContent = config.strings.aiwaiterfailed ||
                        'The AI waiter could not generate a reply, so the scripted next line is used.';
                }
                return '';
            }).catch(function(error) {
                if (roleFeedbackRegion) {
                    roleFeedbackRegion.textContent = config.strings.aiwaiterfailed ||
                        'The AI waiter could not generate a reply, so the scripted next line is used.';
                }
                if (window.console && window.console.error) {
                    window.console.error(error);
                }
                return '';
            });
        };

        var bestAnswerText = function() {
            var best = '';
            var bestScore = -1;
            root.querySelectorAll('input[type=radio]').forEach(function(input) {
                var score = parseInt(input.value, 10) || 0;
                if (score > bestScore) {
                    bestScore = score;
                    best = input.getAttribute('data-answer-text') || '';
                }
            });
            return best;
        };

        var audioBlobToBase64 = function(blob) {
            return new Promise(function(resolve, reject) {
                var reader = new FileReader();
                reader.onloadend = function() {
                    var result = reader.result || '';
                    resolve(String(result).split(',').pop());
                };
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            });
        };

        var updateRoleCompletion = function(complete) {
            roleComplete = complete;
            root.setAttribute('data-role-complete', complete ? '1' : '0');
            if (complete) {
                root.setAttribute('data-role-score', roleEarnedScore || (config.rolecharacter ? config.rolecharacter.score || 0 : 0));
            }
            updateScore(root, config.passinggrade, config.maxgrade);
            updateMissionProgress();
        };

        var sendSpeakingForScore = function(blob) {
            if (!transcriptRegion || !feedbackRegion) {
                return;
            }

            transcriptRegion.textContent = config.strings.speakingscoring || 'Scoring speaking...';
            feedbackRegion.textContent = '';

            audioBlobToBase64(blob).then(function(audio) {
                return Ajax.call([{
                    methodname: 'mod_flwvrroom_score_speaking',
                    args: {
                        cmid: config.cmid,
                        audio: audio,
                        mimetype: blob.type || 'audio/webm',
                        prompt: config.quizquestion || '',
                        targetanswer: bestAnswerText()
                    }
                }])[0];
            }).then(function(response) {
                if (!response.status) {
                    speakingText = '';
                    aiFeedback = '';
                    speakingResults = [];
                    root.setAttribute('data-speaking-complete', '0');
                    transcriptRegion.textContent = response.feedback ||
                        (config.strings.nospeechdetected || 'I could not hear enough speech. Please try recording again.');
                    feedbackRegion.textContent = '';
                    return response;
                }

                speakingText = response.transcript || '';
                aiFeedback = response.feedback || response.rawjson || '';
                root.setAttribute('data-speaking-complete', speakingText !== '' ? '1' : '0');
                speakingResults = [{
                    prompt: config.quizquestion || '',
                    expectedresponse: bestAnswerText(),
                    recognizedresponse: speakingText,
                    feedback: aiFeedback,
                    score: response.totalscore || 0,
                    normalizedscore: normalizeServiceScore(response.totalscore),
                    kpcodes: config.kpcodes || [],
                    rawjson: response.rawjson || ''
                }];
                transcriptRegion.textContent = speakingText || (config.strings.speakingempty || 'No speaking reply yet.');
                feedbackRegion.textContent = aiFeedback;
                updateMissionProgress();
                return response;
            }).catch(function(error) {
                root.setAttribute('data-speaking-complete', '0');
                transcriptRegion.textContent = config.strings.speakingfailed || 'Speaking scoring failed.';
                feedbackRegion.textContent = config.strings.nospeechdetected ||
                    'Please try recording again. If this keeps happening, ask your teacher to check the local scoring service.';
                if (window.console && window.console.error) {
                    window.console.error(error);
                }
            });
        };

        var sendRoleSpeakingForScore = function(blob) {
            if (!roleTranscriptRegion || !roleFeedbackRegion || !config.rolecharacter) {
                return;
            }

            var turn = currentRoleTurn();
            roleTranscriptRegion.textContent = config.strings.speakingscoring || 'Scoring speaking...';
            roleFeedbackRegion.textContent = '';

            audioBlobToBase64(blob).then(function(audio) {
                return Ajax.call([{
                    methodname: 'mod_flwvrroom_score_speaking',
                    args: {
                        cmid: config.cmid,
                        audio: audio,
                        mimetype: blob.type || 'audio/webm',
                        prompt: turn.line || config.rolecharacter.line || '',
                        targetanswer: turn.expectedanswer || config.rolecharacter.expectedanswer || ''
                    }
                }])[0];
            }).then(function(response) {
                if (!response.status) {
                    roleSpeakingText = '';
                    roleAiFeedback = '';
                    updateRoleCompletion(false);
                    roleTranscriptRegion.textContent = response.feedback ||
                        (config.strings.nospeechdetected || 'I could not hear enough speech. Please try recording again.');
                    roleFeedbackRegion.textContent = '';
                    return response;
                }

                roleSpeakingText = response.transcript || '';
                roleAiFeedback = response.feedback || response.rawjson || '';
                if (roleSpeakingText !== '') {
                    var turnNumber = roleTurnIndex + 1;
                    var turnTranscript = roleSpeakingText;
                    var turnFeedback = roleAiFeedback;
                    appendRoleConversation('Learner', turnTranscript, 'learner');
                    if (turnFeedback) {
                        appendRoleConversation(config.strings.aifeedback || 'AI feedback', turnFeedback, 'feedback');
                    }
                    roleSpeakingLog.push('Learner turn ' + turnNumber + ': ' + turnTranscript);
                    roleFeedbackLog.push('Turn ' + turnNumber + ': ' + turnFeedback);
                    roleTurnResults.push({
                        role: config.rolecharacter.name || 'Character',
                        character: config.rolecharacter.name || '',
                        prompt: turn.line || config.rolecharacter.line || '',
                        expectedresponse: turn.expectedanswer || config.rolecharacter.expectedanswer || '',
                        learnerresponse: turnTranscript,
                        feedback: turnFeedback,
                        score: response.totalscore || turn.score || 0,
                        maxscore: response.totalscore ? 100 : (config.maxgrade || 100),
                        normalizedscore: normalizeServiceScore(response.totalscore),
                        kpcodes: turn.kpcodes || config.rolecharacter.kpcodes || []
                    });
                    roleSpeakingText = roleSpeakingLog.join("\n");
                    updateRoleSpeakingExport();
                    roleAiFeedback = roleFeedbackLog.join("\n");
                    roleEarnedScore += parseInt(turn.score, 10) || 0;
                    (turn.kpcodes || []).forEach(function(code) {
                        if (roleCompletedKpcodes.indexOf(code) === -1) {
                            roleCompletedKpcodes.push(code);
                        }
                    });
                    roleConversationHistory.push((config.rolecharacter.name || 'Character') + ': ' + (turn.line || ''));
                    roleConversationHistory.push('Learner: ' + turnTranscript);
                    roleTranscriptRegion.textContent = 'Turn ' + turnNumber + ': ' + turnTranscript;
                    roleFeedbackRegion.textContent = turnFeedback;
                    roleTurnIndex += 1;
                    if (roleTurnIndex >= roleMaxTurns) {
                        updateRoleCompletion(true);
                        if (roleTurnProgressRegion) {
                            roleTurnProgressRegion.textContent = config.strings.roleturncomplete || 'Role play complete.';
                        }
                    } else {
                        updateRoleCompletion(false);
                        requestAiWaiterLine(turn, turnTranscript).then(function(aiLine) {
                            if (aiLine) {
                                roleConversationHistory.push((config.rolecharacter.name || 'Character') + ': ' + aiLine);
                                roleTurns[roleTurnIndex] = {
                                    line: aiLine,
                                    expectedanswer: '',
                                    score: turn.score || (config.rolecharacter.score || 0),
                                    kpcodes: turn.kpcodes || (config.rolecharacter.kpcodes || [])
                                };
                            } else if (!roleTurns[roleTurnIndex]) {
                                roleTurns[roleTurnIndex] = {
                                    line: 'Thank you. Would you like anything else?',
                                    expectedanswer: '',
                                    score: turn.score || (config.rolecharacter.score || 0),
                                    kpcodes: turn.kpcodes || (config.rolecharacter.kpcodes || [])
                                };
                            }
                            renderRoleTurn();
                            if (roleFeedbackRegion && aiLine) {
                                roleFeedbackRegion.textContent = turnFeedback;
                            }
                            return aiLine;
                        });
                    }
                } else {
                    updateRoleCompletion(false);
                    roleTranscriptRegion.textContent = config.strings.speakingempty || 'No speaking reply yet.';
                    roleFeedbackRegion.textContent = roleAiFeedback;
                }
                return response;
            }).catch(function(error) {
                updateRoleCompletion(false);
                roleTranscriptRegion.textContent = config.strings.speakingfailed || 'Speaking scoring failed.';
                roleFeedbackRegion.textContent = config.strings.nospeechdetected ||
                    'Please try recording again. If this keeps happening, ask your teacher to check the local scoring service.';
                if (window.console && window.console.error) {
                    window.console.error(error);
                }
            });
        };

        var startRecording = function(button, transcriptTarget, feedbackTarget, recordLabel, stopLabel, onStop) {
            if (!navigator.mediaDevices || !window.MediaRecorder) {
                if (feedbackTarget) {
                    feedbackTarget.textContent = config.strings.recordingunsupported || 'Audio recording is not available in this browser.';
                }
                return;
            }

            if (recorder && recorder.state === 'recording') {
                recorder.stop();
                button.textContent = recordLabel;
                return;
            }

            navigator.mediaDevices.getUserMedia({audio: true}).then(function(stream) {
                recordingChunks = [];
                recorder = new MediaRecorder(stream);
                recorder.addEventListener('dataavailable', function(event) {
                    if (event.data && event.data.size > 0) {
                        recordingChunks.push(event.data);
                    }
                });
                recorder.addEventListener('stop', function() {
                    stream.getTracks().forEach(function(track) {
                        track.stop();
                    });
                    onStop(new Blob(recordingChunks, {type: recorder.mimeType || 'audio/webm'}));
                });
                recorder.start();
                button.textContent = stopLabel;
                if (transcriptTarget) {
                    transcriptTarget.textContent = config.strings.speakingrecording || 'Recording...';
                }
                if (feedbackTarget) {
                    feedbackTarget.textContent = '';
                }
            }).catch(function(error) {
                if (feedbackTarget) {
                    feedbackTarget.textContent = config.strings.recordingfailed || 'Could not start recording.';
                }
                Notification.exception(error);
            });
        };

        if (speakingButton) {
            speakingButton.addEventListener('click', function() {
                startRecording(
                    speakingButton,
                    transcriptRegion,
                    feedbackRegion,
                    config.strings.recordspeaking || 'Record reply',
                    config.strings.stopspeaking || 'Stop recording',
                    sendSpeakingForScore
                );
            });
        }

        if (roleButton && roleCard) {
            roleButton.addEventListener('click', function() {
                roleCard.hidden = false;
                root.classList.add('is-role-card-open');
            });
        }

        if (closeRoleButton && roleCard) {
            closeRoleButton.addEventListener('click', function() {
                roleCard.hidden = true;
                root.classList.remove('is-role-card-open');
            });
        }

        if (roleCard) {
            roleCard.addEventListener('pointerdown', function(event) {
                event.stopPropagation();
            });
        }

        if (roleSpeakingButton) {
            roleSpeakingButton.addEventListener('click', function() {
                startRecording(
                    roleSpeakingButton,
                    roleTranscriptRegion,
                    roleFeedbackRegion,
                    config.strings.recordrolereply || 'Record role reply',
                    config.strings.stoprolereply || 'Stop role reply',
                    sendRoleSpeakingForScore
                );
            });
        }

        if (roleTurnAppendButton) {
            roleTurnAppendButton.addEventListener('click', function() {
                var builderValue = function(name) {
                    var field = root.querySelector('[data-builder-field="' + name + '"]');
                    return field ? String(field.value || '').trim() : '';
                };
                var line = builderValue('line');
                var answer = builderValue('answer');
                var score = parseInt(builderValue('score'), 10);
                var kpcodes = builderValue('kpcodes').replace(/\s*[\r\n]+\s*/g, ',');

                if (!line) {
                    return;
                }

                appendEditorLine('roleturns', [
                    line.replace(/\|/g, '/'),
                    answer.replace(/\|/g, '/'),
                    isNaN(score) ? 20 : clamp(score, 0, 100),
                    kpcodes.replace(/\|/g, '/')
                ].join('|'));
                renderRoleTurnPreview();
            });
        }

        if (hotspotAppendButton) {
            hotspotAppendButton.addEventListener('click', function() {
                var field = getEditorField('customhotspots');
                if (!field) {
                    return;
                }
                var line = hotspotLineFromBuilder();
                appendEditorLine('customhotspots', line);
                createLiveHotspotFromLine(line);
                selectedHotspotIndex = splitLines(field.value).length - 1;
                renderHotspotPreview();
            });
        }

        if (hotspotUpdateButton) {
            hotspotUpdateButton.addEventListener('click', function() {
                var field = getEditorField('customhotspots');
                if (!field) {
                    return;
                }
                var lines = splitLines(field.value);
                if (selectedHotspotIndex < 0 || selectedHotspotIndex >= lines.length) {
                    selectedHotspotIndex = lines.length ? 0 : -1;
                }
                if (selectedHotspotIndex >= 0) {
                    var oldLine = lines[selectedHotspotIndex];
                    lines[selectedHotspotIndex] = hotspotLineFromBuilder();
                    field.value = lines.join("\n");
                    field.dispatchEvent(new Event('input', {bubbles: true}));
                    syncLiveHotspotFromLine(oldLine, lines[selectedHotspotIndex]);
                    renderRotation();
                    updateScore(root, config.passinggrade, config.maxgrade);
                    updateMissionProgress();
                    renderHotspotPreview();
                }
            });
        }

        if (hotspotDeleteButton) {
            hotspotDeleteButton.addEventListener('click', function() {
                var field = getEditorField('customhotspots');
                if (!field) {
                    return;
                }
                var lines = splitLines(field.value);
                if (selectedHotspotIndex >= 0 && selectedHotspotIndex < lines.length) {
                    var removedLine = lines[selectedHotspotIndex];
                    lines.splice(selectedHotspotIndex, 1);
                    field.value = lines.join("\n");
                    selectedHotspotIndex = Math.min(selectedHotspotIndex, lines.length - 1);
                    field.dispatchEvent(new Event('input', {bubbles: true}));
                    removeLiveHotspotForLine(removedLine);
                    renderRotation();
                    updateScore(root, config.passinggrade, config.maxgrade);
                    updateMissionProgress();
                    renderHotspotPreview();
                }
            });
        }

        if (insertKpButton) {
            insertKpButton.addEventListener('click', insertKpCode);
        }

        if (applyScenarioTemplateButton) {
            applyScenarioTemplateButton.addEventListener('click', applyScenarioTemplate);
        }

        if (bindObjectRefButton) {
            bindObjectRefButton.addEventListener('click', function() {
                if (!objectBrowserSelect || !objectBrowserSelect.value) {
                    return;
                }
                setHotspotBuilderValue('objectref', objectBrowserSelect.value);
                var field = getEditorField('customhotspots');
                if (field && selectedHotspotIndex >= 0) {
                    var lines = splitLines(field.value);
                    if (selectedHotspotIndex < lines.length) {
                        var oldLine = lines[selectedHotspotIndex];
                        lines[selectedHotspotIndex] = hotspotLineFromBuilder();
                        field.value = lines.join("\n");
                        field.dispatchEvent(new Event('input', {bubbles: true}));
                        syncLiveHotspotFromLine(oldLine, lines[selectedHotspotIndex]);
                        renderRotation();
                        renderHotspotPreview();
                    }
                }
                if (roomEditorStatus) {
                    roomEditorStatus.textContent = (config.strings.objectbound || 'Object bound: {$a}')
                        .replace('{$a}', objectBrowserSelect.value);
                }
            });
        }

        if (exportScenarioButton && scenarioJsonField) {
            exportScenarioButton.addEventListener('click', function() {
                scenarioJsonField.value = JSON.stringify(editorScenarioData(), null, 2);
                scenarioJsonField.select();
                if (roomEditorStatus) {
                    roomEditorStatus.textContent = config.strings.scenarioexported || 'Scenario JSON exported.';
                }
            });
        }

        if (importScenarioButton && scenarioJsonField) {
            importScenarioButton.addEventListener('click', function() {
                try {
                    applyScenarioData(JSON.parse(scenarioJsonField.value || '{}'));
                    if (roomEditorStatus) {
                        roomEditorStatus.textContent = config.strings.scenarioimported ||
                            'Scenario JSON imported. Save the editor to keep it.';
                    }
                } catch (error) {
                    if (roomEditorStatus) {
                        roomEditorStatus.textContent = config.strings.scenarioimportfailed ||
                            'Scenario JSON could not be imported.';
                    }
                }
            });
        }

        var customHotspotsField = getEditorField('customhotspots');
        if (customHotspotsField) {
            customHotspotsField.addEventListener('input', renderHotspotPreview);
            renderHotspotPreview();
        }
        var roleTurnsField = getEditorField('roleturns');
        if (roleTurnsField) {
            roleTurnsField.addEventListener('input', renderRoleTurnPreview);
            renderRoleTurnPreview();
        }

        if (xrStatus) {
            if (navigator.xr && navigator.xr.isSessionSupported) {
                Promise.all([
                    navigator.xr.isSessionSupported('immersive-vr').catch(function() {
                        return false;
                    }),
                    navigator.xr.isSessionSupported('immersive-ar').catch(function() {
                        return false;
                    })
                ]).then(function(results) {
                    var vr = results[0];
                    var ar = results[1];
                    if (vr && webxrButton) {
                        webxrButton.hidden = false;
                    }
                    xrStatus.textContent = (config.strings.xravailable || 'XR: {$a}')
                        .replace('{$a}', [
                            vr ? 'VR' : '',
                            ar ? 'AR' : ''
                        ].filter(function(item) {
                            return item !== '';
                        }).join(', ') || (config.strings.xrnotavailable || 'not available'));
                });
            } else {
                xrStatus.textContent = config.strings.desktopfallback ||
                    (config.strings.xrnotavailable || 'XR not available in this browser.');
            }
        }

        if (webxrButton) {
            webxrButton.addEventListener('click', function() {
                if (!navigator.xr || !navigator.xr.requestSession || !threeState || !threeState.enterWebXR) {
                    if (xrStatus) {
                        xrStatus.textContent = config.strings.desktopfallback ||
                            (config.strings.xrnotavailable || 'XR not available in this browser.');
                    }
                    return;
                }
                threeState.enterWebXR()
                    .then(function(session) {
                        if (xrStatus) {
                            xrStatus.textContent = config.strings.xrstarted || 'Immersive VR session started.';
                        }
                        if (session && session.addEventListener) {
                            session.addEventListener('end', function() {
                                if (xrStatus) {
                                    xrStatus.textContent = (config.strings.xravailable || 'XR available: {$a}')
                                        .replace('{$a}', 'VR');
                                }
                            });
                        }
                    })
                    .catch(function() {
                        if (xrStatus) {
                            xrStatus.textContent = config.strings.xrstartfailed || 'Could not start immersive VR.';
                        }
                    });
            });
        }

        if (roomEditorSave) {
            roomEditorSave.addEventListener('click', function() {
                var editorValue = function(name) {
                    var field = getEditorField(name);
                    if (field && field.type === 'checkbox') {
                        return field.checked;
                    }
                    return field ? field.value : '';
                };
                roomEditorSave.disabled = true;
                if (roomEditorStatus) {
                    roomEditorStatus.textContent = config.strings.roomeditorsaving || 'Saving room editor...';
                }

                Ajax.call([{
                    methodname: 'mod_flwvrroom_save_room_editor',
                    args: {
                        cmid: config.cmid,
                        kpcodes: editorValue('kpcodes'),
                        customhotspots: editorValue('customhotspots'),
                        custommissiontitle: editorValue('custommissiontitle'),
                        custommissiontext: editorValue('custommissiontext'),
                        customquizquestion: editorValue('customquizquestion'),
                        customanswers: editorValue('customanswers'),
                        rolecharacterposition: editorValue('rolecharacterposition'),
                        rolecharacterline: editorValue('rolecharacterline'),
                        roleexpectedanswer: editorValue('roleexpectedanswer'),
                        rolekpcodes: editorValue('rolekpcodes'),
                        rolescore: parseInt(editorValue('rolescore'), 10) || 0,
                        roleturns: editorValue('roleturns'),
                        roleaienabled: editorValue('roleaienabled'),
                        roleaiturns: parseInt(editorValue('roleaiturns'), 10) || 3,
                        roleaipersonality: editorValue('roleaipersonality'),
                        roleaidifficulty: editorValue('roleaidifficulty') || 'friendly',
                        roleaitargetpattern: editorValue('roleaitargetpattern'),
                        roleaimaxretries: intOrDefault(editorValue('roleaimaxretries'), 1),
                        completionrequirehotspots: editorValue('completionrequirehotspots'),
                        completionrequirespeaking: editorValue('completionrequirespeaking'),
                        completionrequirerole: editorValue('completionrequirerole'),
                        completionminscore: intOrDefault(editorValue('completionminscore'), config.passinggrade || 70)
                    }
                }])[0].then(function(response) {
                    if (roomEditorStatus) {
                        roomEditorStatus.textContent = response.message ||
                            (config.strings.roomeditorsaved || 'Room editor saved. Refresh the room to see all saved changes.');
                    }
                    return response;
                }).catch(function(error) {
                    if (roomEditorStatus) {
                        roomEditorStatus.textContent = config.strings.roomeditorsavefailed ||
                            'The room editor changes could not be saved.';
                    }
                    Notification.exception(error);
                }).then(function() {
                    roomEditorSave.disabled = false;
                });
            });
        }

        var save = root.querySelector('[data-action="save-attempt"]');
        var status = root.querySelector('[data-region="status"]');
        updateMissionProgress();

        save.addEventListener('click', function() {
            var result = updateScore(root, config.passinggrade, config.maxgrade);
            var issues = completionIssues(result.score);
            var submitKpcodes = (config.kpcodes || []).slice();
            if (roleComplete) {
                var roleKps = roleCompletedKpcodes.length ? roleCompletedKpcodes :
                    (config.rolecharacter && config.rolecharacter.kpcodes ? config.rolecharacter.kpcodes : []);
                submitKpcodes = submitKpcodes.concat(roleKps);
            }
            var submitSpeakingText = speakingText;
            if (roleSpeakingText) {
                submitSpeakingText = (submitSpeakingText ? submitSpeakingText + "\n\n" : '') +
                    'Role play (' + (config.rolecharacter.name || 'Character') + '): ' + roleSpeakingText;
            }
            var submitAiFeedback = aiFeedback;
            if (roleAiFeedback) {
                submitAiFeedback = (submitAiFeedback ? submitAiFeedback + "\n\n" : '') +
                    'Role play feedback: ' + roleAiFeedback;
            }
            save.disabled = true;
            status.textContent = 'Saving...';

            Ajax.call([{
                methodname: 'mod_flwvrroom_submit_attempt',
                args: {
                    cmid: config.cmid,
                    score: result.score,
                    completedobjects: result.completed.join(','),
                    kpcodes: submitKpcodes.join(','),
                    speakingtext: submitSpeakingText,
                    aifeedback: submitAiFeedback,
                    hotspotsjson: JSON.stringify(completedHotspotPayload()),
                    roleturnsjson: JSON.stringify(roleTurnResults),
                    speakingjson: JSON.stringify(speakingResults),
                    taskcomplete: issues.length === 0,
                    durationseconds: Math.round((Date.now() - started) / 1000)
                }
            }])[0].then(function(response) {
                var bestScore = root.querySelector('[data-region="best-score"]');
                bestScore.textContent = Math.max(parseInt(bestScore.textContent, 10) || 0, response.score);
                status.textContent = config.strings.saved + ' Score: ' + response.score + (issues.length === 0 ?
                    ' / ' + (config.strings.completionready || 'Completion ready.') :
                    ' / ' + issues.join(' '));
                return response;
            }).catch(function(error) {
                status.textContent = config.strings.savefailed;
                Notification.exception(error);
            }).then(function() {
                save.disabled = false;
            });
        });
    };

    return {
        init: init
    };
});
