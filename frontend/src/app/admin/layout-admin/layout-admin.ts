import { Component } from '@angular/core';
import { RouterLink, RouterOutlet } from '@angular/router';

@Component({
  selector: 'app-layout-admin',
  imports: [RouterLink, RouterOutlet],
  templateUrl: './layout-admin.html',
})
export class LayoutAdmin {}
