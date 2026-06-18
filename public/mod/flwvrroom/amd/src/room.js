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

        var stage = root.querySelector('[data-region="panorama-stage"]');
        var panorama = root.querySelector('[data-region="panorama"]');
        if (stage) {
            var rotation = 50;
            var offsetPx = 0;
            var visibleSpan = 50;
            var hotspots = root.querySelectorAll('[data-hotspot]');
            var threeState = null;
            var roomMode = config.roommode || stage.getAttribute('data-room-mode') || 'panorama';

            var renderRotation = function() {
                if (panorama) {
                    panorama.style.backgroundPosition = offsetPx + 'px center';
                }
                if (threeState) {
                    threeState.lon = (rotation / 100) * 360;
                    threeState.render();
                    if (threeState.projectHotspots) {
                        threeState.projectHotspots(hotspots);
                        return;
                    }
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
                    setupBuiltin3dRoom(THREE, container);
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
                        var phi = THREE.MathUtils.degToRad(90 - state.lat);
                        var theta = THREE.MathUtils.degToRad(state.lon);
                        camera.lookAt(
                            500 * Math.sin(phi) * Math.cos(theta),
                            500 * Math.cos(phi),
                            500 * Math.sin(phi) * Math.sin(theta)
                        );
                        renderer.render(scene, camera);
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

            var setupBuiltin3dRoom = function(THREE, container) {
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
                cup.userData.hotspotKey = 'cup';
                scene.add(cup);
                clickableObjects.push(cup);

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
                waiter.userData.hotspotKey = 'waiter';
                scene.add(waiter);
                clickableObjects.push(body, head);
                body.userData.hotspotKey = 'waiter';
                head.userData.hotspotKey = 'waiter';

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

                renderer.domElement.addEventListener('click', function(event) {
                    setPointerFromEvent(event);
                    var hit = raycaster.intersectObjects(clickableObjects, true)[0];
                    if (!hit || !hit.object.userData.hotspotKey) {
                        return;
                    }
                    var button = findHotspotButton(hit.object.userData.hotspotKey);
                    if (button) {
                        button.click();
                    }
                });

                var state = {
                    lon: 180,
                    bounds: {
                        minX: -4.8,
                        maxX: 4.8,
                        minZ: -3.8,
                        maxZ: 5.2
                    },
                    render: function() {
                        var yaw = THREE.MathUtils.degToRad(state.lon - 180);
                        camera.lookAt(
                            camera.position.x + Math.sin(yaw) * 10,
                            1.45,
                            camera.position.z - Math.cos(yaw) * 10
                        );
                        renderer.render(scene, camera);
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
                    }
                };

                threeState = state;
                renderRotation();

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
                var raycaster = new THREE.Raycaster();
                var pointer = new THREE.Vector2();
                var setPointerFromEvent = function(event) {
                    var rect = renderer.domElement.getBoundingClientRect();
                    pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
                    pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
                    raycaster.setFromCamera(pointer, camera);
                };

                var loader = new GLTFLoader();
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
                    renderRotation();
                }, null, function() {
                    // The teacher-facing Moodle file manager keeps the source file visible for correction.
                });

                var state = {
                    lon: 180,
                    bounds: {
                        minX: -8,
                        maxX: 8,
                        minZ: -8,
                        maxZ: 8
                    },
                    render: function() {
                        var yaw = THREE.MathUtils.degToRad(state.lon - 180);
                        camera.lookAt(
                            camera.position.x + Math.sin(yaw) * 10,
                            1.35,
                            camera.position.z - Math.cos(yaw) * 10
                        );
                        renderer.render(scene, camera);
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
                    }
                };

                threeState = state;
                renderRotation();

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
                if (roomMode === 'uploaded3d') {
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
                if (event.target.closest('button')) {
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

            if (helperButton && helperStatus) {
                helperButton.addEventListener('click', function() {
                    helperActive = !helperActive;
                    root.classList.toggle('is-position-helper-active', helperActive);
                    helperStatus.textContent = helperActive ?
                        (config.strings.positionhelperactive || 'Click the room to copy x/y') :
                        (config.strings.positionhelperidle || 'Click to capture x/y');
                });

                stage.addEventListener('click', function(event) {
                    if (!helperActive || event.target.closest('button') || event.target.closest('.flwvrroom-hotspot-card')) {
                        return;
                    }

                    var value = '';
                    var copied = config.strings.positionhelpercopied || 'Copied x/y: {$a}';
                    if ((roomMode === 'builtin3d' || roomMode === 'uploaded3d') && threeState && threeState.capturePosition) {
                        var point = threeState.capturePosition(event);
                        if (point) {
                            value = point.x.toFixed(2) + '|' + point.y.toFixed(2) + '|' + point.z.toFixed(2);
                            copied = config.strings.positionhelpercopied3d || 'Copied 3D x/y/z: {$a}';
                        }
                    }

                    if (value === '') {
                        var rect = stage.getBoundingClientRect();
                        var x = clamp(((event.clientX - rect.left) / rect.width) * 100, 0, 100).toFixed(1);
                        var y = clamp(((event.clientY - rect.top) / rect.height) * 100, 0, 100).toFixed(1);
                        value = x + '|' + y;
                    }

                    helperStatus.textContent = copied.replace('{$a}', value);
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
            });
        });

        root.querySelectorAll('input[type=radio]').forEach(function(input) {
            input.addEventListener('change', function() {
                updateScore(root, config.passinggrade, config.maxgrade);
            });
        });

        var speakingText = '';
        var aiFeedback = '';
        var recorder = null;
        var recordingChunks = [];
        var speakingButton = root.querySelector('[data-action="record-speaking"]');
        var transcriptRegion = root.querySelector('[data-region="speaking-transcript"]');
        var feedbackRegion = root.querySelector('[data-region="speaking-feedback"]');

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
                    transcriptRegion.textContent = response.feedback ||
                        (config.strings.nospeechdetected || 'I could not hear enough speech. Please try recording again.');
                    feedbackRegion.textContent = '';
                    return response;
                }

                speakingText = response.transcript || '';
                aiFeedback = response.feedback || response.rawjson || '';
                transcriptRegion.textContent = speakingText || (config.strings.speakingempty || 'No speaking reply yet.');
                feedbackRegion.textContent = aiFeedback;
                return response;
            }).catch(function(error) {
                transcriptRegion.textContent = config.strings.speakingfailed || 'Speaking scoring failed.';
                feedbackRegion.textContent = config.strings.nospeechdetected ||
                    'Please try recording again. If this keeps happening, ask your teacher to check the local scoring service.';
                if (window.console && window.console.error) {
                    window.console.error(error);
                }
            });
        };

        if (speakingButton) {
            speakingButton.addEventListener('click', function() {
                if (!navigator.mediaDevices || !window.MediaRecorder) {
                    if (feedbackRegion) {
                        feedbackRegion.textContent = config.strings.recordingunsupported || 'Audio recording is not available in this browser.';
                    }
                    return;
                }

                if (recorder && recorder.state === 'recording') {
                    recorder.stop();
                    speakingButton.textContent = config.strings.recordspeaking || 'Record reply';
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
                        sendSpeakingForScore(new Blob(recordingChunks, {type: recorder.mimeType || 'audio/webm'}));
                    });
                    recorder.start();
                    speakingButton.textContent = config.strings.stopspeaking || 'Stop recording';
                    if (transcriptRegion) {
                        transcriptRegion.textContent = config.strings.speakingrecording || 'Recording...';
                    }
                    if (feedbackRegion) {
                        feedbackRegion.textContent = '';
                    }
                }).catch(function(error) {
                    if (feedbackRegion) {
                        feedbackRegion.textContent = config.strings.recordingfailed || 'Could not start recording.';
                    }
                    Notification.exception(error);
                });
            });
        }

        var save = root.querySelector('[data-action="save-attempt"]');
        var status = root.querySelector('[data-region="status"]');

        save.addEventListener('click', function() {
            var result = updateScore(root, config.passinggrade, config.maxgrade);
            save.disabled = true;
            status.textContent = 'Saving...';

            Ajax.call([{
                methodname: 'mod_flwvrroom_submit_attempt',
                args: {
                    cmid: config.cmid,
                    score: result.score,
                    completedobjects: result.completed.join(','),
                    kpcodes: (config.kpcodes || []).join(','),
                    speakingtext: speakingText,
                    aifeedback: aiFeedback,
                    taskcomplete: result.score >= config.passinggrade,
                    durationseconds: Math.round((Date.now() - started) / 1000)
                }
            }])[0].then(function(response) {
                var bestScore = root.querySelector('[data-region="best-score"]');
                bestScore.textContent = Math.max(parseInt(bestScore.textContent, 10) || 0, response.score);
                status.textContent = config.strings.saved + ' Score: ' + response.score + (response.passed ? ' / Passed' : ' / Try again');
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
