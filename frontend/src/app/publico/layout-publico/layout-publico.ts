import { Component } from '@angular/core';
import { RouterLink, RouterOutlet } from '@angular/router';

@Component({
  selector: 'app-layout-publico',
  imports: [RouterLink, RouterOutlet],
  templateUrl: './layout-publico.html',
})
export class LayoutPublico {
  protected readonly ano = new Date().getFullYear();
}
