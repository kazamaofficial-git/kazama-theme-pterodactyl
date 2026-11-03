import React from 'react';

// Kazama Cloud: navigasi atas (tab) digantikan oleh sidebar/drawer di NavigationBar.
// Elemen tersembunyi dipertahankan agar transisi bawaan Pterodactyl tetap punya node DOM.
const SubNavigation = (_props: React.PropsWithChildren<unknown>) => <div style={{ display: 'none' }} />;

export default SubNavigation;
